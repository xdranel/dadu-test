<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SidangTaDraftFinalizer
{
    public function __construct(private SidangTaDraftAccess $access,
                                private SidangTaAcademicSnapshot $snapshots) {}

    public function submit(User $user, SidangTaRegistrationDraft $draft, int $version,
                           ?int $expectedSourceId = null): SidangTaRegistration
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $retired = [];

        $registration = DB::transaction(function () use ($user, $draft, $version,
            $expectedSourceId, $student, &$retired) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            $sourceId = $locked->source_registration_id === null ? null : (int) $locked->source_registration_id;
            abort_unless($sourceId === $expectedSourceId, 409, 'Draft tidak cocok dengan jenis pengajuan ini.');
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang sebelum mengirim.');

            // Harus sebelum check(active), source needs_review, periode dan kelengkapan.
            if ($locked->status === SidangTaRegistrationDraft::STATUS_CONSUMED) {
                $existing = $locked->submittedRegistration()->firstOrFail();
                abort_unless($existing->student_number === $student->student_number, 403);
                return $existing; // Tidak mengubah keputusan reviewer atau menulis log ulang.
            }

            $this->access->check($user, $locked, true);
            // Temporary rollout guard remove only when the meet UI and final eligibility are wired.
            abort_unless($locked->requirements_version === 1, 422,
                'Pengiriman draft ini belum tersedia. Data dan berkas tetap tersimpan.');
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
            if (Requirements::firstIncompleteStep($locked) !== null) {
                throw ValidationException::withMessages(['draft' => 'Data atau berkas belum lengkap. Buka form dan lengkapi kembali.']);
            }
            $source = $sourceId === null ? null
                : $locked->sourceRegistration()->lockForUpdate()->firstOrFail();
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            if (!$source && !$period->isOpen()) {
                throw ValidationException::withMessages(['draft' => 'Periode telah ditutup. Pilih periode terbuka dan tinjau ulang.']);
            }
            if ($source) {
                abort_unless((int) $source->sidang_ta_period_id === (int) $locked->sidang_ta_period_id, 409);
            }
            $otherActive = SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review']);
            if ($source) $otherActive->where('id', '!=', $source->id);
            abort_if($otherActive->exists(), 409, 'Anda masih memiliki pengajuan aktif lain.');

            $payload = $locked->payload;
            $types = array_merge(Requirements::CAPTURES, Requirements::PAYMENT,
                Requirements::typesForStep(4, $payload['diploma_biodata_accurate']));
            $documents = $locked->documents()->get();
            $actual = $documents->pluck('document_type')->all();
            $expected = $types;
            sort($actual); sort($expected);
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['draft' => 'Daftar berkas tidak sesuai persyaratan aktif.']);
            }

            $registration = $source ?? new SidangTaRegistration();
            $oldStatus = $source?->status;
            $oldPaths = $source ? $source->documents()->pluck('file_path')->all() : [];
            $registration->fill([
                'sidang_ta_period_id' => $period->id,
                'student_number' => $student->student_number, 'student_name' => $student->name,
                'study_program' => $student->study_program,
                'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
                'graduation_payment_date' => $payload['graduation_payment_date'],
                'diploma_biodata_accurate' => $payload['diploma_biodata_accurate'],
                'academic_snapshot' => $this->snapshots->forStudent($student),
                'requirements_version' => $locked->requirements_version,
                'status' => 'submitted', 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            if (!$source) $registration->submitted_by = $user->id;
            $registration->lock_version = $source ? $source->lock_version + 1 : 1;
            $registration->save();

            foreach ($documents as $document) {
                $registration->documents()->updateOrCreate(['document_type' => $document->document_type], [
                    'file_name' => $document->file_name, 'file_path' => $document->file_path,
                    'file_size' => $document->file_size, 'file_type' => $document->file_type,
                    'status' => 'pending', 'notes' => null,
                ]);
            }
            // Lepaskan FK source_document_id sebelum menghapus row final obsolete.
            $locked->documents()->delete();
            $registration->documents()->whereNotIn('document_type', $types)->delete();
            $retired = array_values(array_diff($oldPaths, $documents->pluck('file_path')->all()));
            $registration->logAction($source ? 'resubmitted' : 'submitted', $oldStatus,
                'submitted', $source ? 'Mahasiswa mengirim revisi.' : 'Pendaftaran dikirim mahasiswa.', $user->id);
            $locked->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
            $locked->submitted_registration_id = $registration->id;
            $locked->save(); // Version draft tetap untuk identitas replay.

            return $registration;
        });

        // Hanya sesudah transaksi final sukses. Jika DB gagal, tidak ada file yang dihapus.
        foreach (array_unique($retired) as $path) {
            try {
                if (SidangTaRegistrationDocument::where('file_path', $path)->exists()
                    || SidangTaRegistrationDraftDocument::where('file_path', $path)->exists()) {
                    continue;
                }
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup finalisasi perlu diulang.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup finalisasi gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
        return $registration;
    }
}
