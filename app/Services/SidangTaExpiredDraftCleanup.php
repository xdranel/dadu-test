<?php

namespace App\Services;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SidangTaExpiredDraftCleanup
{
    public function run(bool $dryRun = false): array
    {
        $summary = ['eligible_drafts' => 0, 'deleted_drafts' => 0];
        SidangTaRegistrationDraft::where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)
            ->whereHas('period', fn ($query) => $query->where('close_at', '<', now()))
            ->select(['id', 'student_number'])->chunkById(100, function ($drafts) use ($dryRun, &$summary) {
                foreach ($drafts as $candidate) {
                    $eligible = DB::transaction(function () use ($candidate, $dryRun) {
                        // Same lock order as submission and revision expiry.
                        Student::where('student_number', $candidate->student_number)->lockForUpdate()->first();
                        $draft = SidangTaRegistrationDraft::whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$draft || $draft->student_number !== $candidate->student_number
                            || $draft->status !== SidangTaRegistrationDraft::STATUS_ACTIVE) return false;
                        if ($draft->source_registration_id !== null) {
                            SidangTaRegistration::whereKey($draft->source_registration_id)->lockForUpdate()->first();
                        }
                        $period = SidangTaPeriod::whereKey($draft->sidang_ta_period_id)->lockForUpdate()->first();
                        if (!$period || !$period->close_at->lt(now())) return false;
                        if ($dryRun) return true;

                        $paths = $draft->documents()->whereNull('source_document_id')->pluck('file_path')->all();
                        $draft->delete();
                        DB::afterCommit(function () use ($paths) {
                            foreach (array_unique($paths) as $path) {
                                try {
                                    if (SidangTaRegistrationDocument::where('file_path', $path)->exists()
                                        || SidangTaRegistrationDraftDocument::where('file_path', $path)->exists()) continue;
                                    if (!Storage::disk('local')->delete($path)) {
                                        Log::warning('Berkas orphan draft kedaluwarsa gagal dihapus; pulihkan manual menggunakan path log karena metadata draft sudah dihapus.', ['path' => $path]);
                                    }
                                } catch (Throwable $exception) {
                                    Log::warning('Berkas orphan draft kedaluwarsa gagal dihapus; pulihkan manual menggunakan path log karena metadata draft sudah dihapus.', ['path' => $path, 'error' => $exception->getMessage()]);
                                }
                            }
                        });
                        return true;
                    }, 3);
                    if ($eligible) {
                        $summary['eligible_drafts']++;
                        if (!$dryRun) $summary['deleted_drafts']++;
                    }
                }
            }, 'id');
        return $summary;
    }
}
