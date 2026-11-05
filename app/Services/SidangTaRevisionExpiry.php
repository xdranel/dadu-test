<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\DB;

class SidangTaRevisionExpiry
{
    public const NOTE = 'Ditolak otomatis karena revisi tidak dikirim sampai batas akhir periode Sidang TA.';

    public function run(): int
    {
        $count = 0;
        SidangTaRegistration::query()->where('requirements_version', 2)->where('status', 'needs_review')
            ->whereHas('period', fn ($query) => $query->where('close_at', '<', now()))
            ->select(['id'])->chunkById(100, function ($registrations) use (&$count) {
                foreach ($registrations as $registration) {
                    if ($this->expire((int) $registration->id)) $count++;
                }
            });
        return $count;
    }

    public function expire(int $id): bool
    {
        $candidate = SidangTaRegistration::select(['id', 'student_number'])->find($id);
        if (!$candidate) return false;

        return DB::transaction(function () use ($candidate) {
            // Urutan sama dengan jalur revisi: Student -> draft -> pengajuan -> periode.
            // Pengajuan historis masih dapat ditolak walaupun master Student tidak tersedia.
            Student::where('student_number', $candidate->student_number)->lockForUpdate()->first();
            SidangTaRegistrationDraft::where('source_registration_id', $candidate->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->orderBy('id')->lockForUpdate()->get();
            $registration = SidangTaRegistration::whereKey($candidate->id)->lockForUpdate()->first();
            if (!$registration || $registration->student_number !== $candidate->student_number
                || $registration->requirements_version !== 2 || $registration->status !== 'needs_review') return false;
            if (!SidangTaRevisionDeadline::hasExpired($registration, true)) return false;

            $registration->status = 'rejected';
            $registration->review_notes = self::NOTE;
            $registration->reviewed_by = null;
            $registration->reviewed_at = now();
            $registration->lock_version++;
            $registration->save();
            $registration->logAction('rejected', 'needs_review', 'rejected', self::NOTE, null);
            // Tidak mengubah draft, dokumen, snapshot, atau file. Discard tetap milik mahasiswa.
            return true;
        }, 3);
    }
}
