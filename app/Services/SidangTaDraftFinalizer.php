<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SidangTaDraftFinalizer
{
    public function __construct(private SidangTaDraftAccess $access,
                                private SidangTaAcademicSnapshot $snapshots,
                                private SidangTaAdmissionFacts $facts) {}

    public function submit(User $user, SidangTaRegistrationDraft $draft, int $version,
                           ?int $expectedSourceId = null, bool $declarationAccepted = false): SidangTaRegistration
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $retired = [];

        $registration = DB::transaction(function () use ($user, $draft, $version,
            $expectedSourceId, $declarationAccepted, $student, &$retired) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            $sourceId = $locked->source_registration_id === null ? null : (int) $locked->source_registration_id;
            abort_unless($sourceId === $expectedSourceId, 409, 'Draft tidak cocok dengan jenis pengajuan ini.');
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang sebelum mengirim.');

            Requirements::assertKnownVersion($locked);

            // Harus sebelum check(active), source needs_review, periode dan kelengkapan.
            if ($locked->status === SidangTaRegistrationDraft::STATUS_CONSUMED) {
                $existing = $locked->submittedRegistration()->firstOrFail();
                abort_unless($existing->requirements_version === 2, 409,
                    'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
                abort_unless($existing->student_number === $student->student_number, 403);
                return $existing; // Tidak mengubah keputusan reviewer atau menulis log ulang.
            }

            if (!$declarationAccepted) {
                throw ValidationException::withMessages(['declaration_accepted' =>
                    'Anda wajib menyetujui pernyataan sebelum mengirim pengajuan.']);
            }
            $this->access->check($user, $locked, true);
            $targeted = Revision::isTargeted($locked);
            abort_unless($locked->reviewed_version === $locked->version, 409, 'Tinjau draft terlebih dahulu.');
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

            $payload = $locked->payload ?? [];
            // One fresh decision supplies gates, required files and the stored snapshot.
            $admission = $this->facts->forStudent($student);
            $errors = [];
            foreach ($admission['academic']['blockers'] as $key => $message) {
                $errors['eligibility.'.$key] = $message;
            }
            if (!in_array($admission['eprt']['mode'], ['automatic', 'waiver'], true)) {
                $errors['eligibility.eprt'] = $admission['eprt']['message'];
            }
            if ($errors !== []) throw ValidationException::withMessages($errors);
            if ($targeted) Revision::assertTransferReady($locked, $admission);
            $rules = [];
            foreach (range(1, 4) as $step) $rules = array_merge($rules, Requirements::scalarRules($step, $payload));
            $validator = Validator::make($payload, $rules, [
                'pdm_submission_date.required' => 'Tanggal pengajuan PDM wajib diisi.',
                'pdm_submission_date.date' => 'Tanggal pengajuan PDM tidak valid.',
                'pdm_submission_date.before_or_equal' => 'Tanggal pengajuan PDM tidak boleh melewati hari ini.',
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray() + [
                    'draft' => 'Data belum lengkap. Buka form dan lengkapi kembali.',
                ]);
            }
            $types = $targeted ? Revision::effectiveTypes($locked, $admission)
                : Meet::allTypes((bool) $payload['diploma_biodata_accurate'], $admission['eprt']['mode']);
            $allDocuments = $locked->documents()->lockForUpdate()->get();
            // A formerly necessary waiver may become obsolete after a qualifying test.
            // Only an explicit successful submission retires it failed submissions keep it.
            $documents = $admission['eprt']['mode'] === 'automatic'
                ? $allDocuments->reject(fn ($document) => $document->document_type === Meet::WAIVER)->values()
                : $allDocuments;
            $actual = $documents->pluck('document_type')->all();
            $expected = $types;
            sort($actual); sort($expected);
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['draft' => 'Daftar berkas tidak sesuai persyaratan aktif. Buka form dan tinjau ulang.']);
            }
            foreach ($documents as $document) {
                if (!Storage::disk('local')->exists($document->file_path)) {
                    throw ValidationException::withMessages(['documents.'.$document->document_type =>
                        'Berkas tidak ditemukan. Unggah ulang dan tinjau kembali.']);
                }
            }
            $snapshot = array_replace($this->snapshots->forStudent($student, $admission), [
                'passed_credits' => $admission['facts']['passed_credits'],
                'tak_score' => $admission['facts']['tak_score'],
                'eprt_score' => $admission['eprt_facts']['score'], 'eprt_facts' => $admission['eprt_facts'],
                'sidang_tingkat_2' => 'Lulus', 'skpi_status' => 'Disetujui',
                'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar',
                'admission' => $admission + ['checked_at' => now()->toIso8601String()],
            ]);

            $registration = $source ?? new SidangTaRegistration();
            $oldStatus = $source?->status;
            $oldPaths = $source ? $source->documents()->pluck('file_path')->all() : [];
            $registration->fill([
                'sidang_ta_period_id' => $period->id,
                'student_number' => $student->student_number,
                'student_name' => $targeted ? $source->student_name : $student->name,
                'study_program' => $targeted ? $source->study_program : $student->study_program,
                'student_phone' => $payload['student_phone'], 'parent_phone' => $payload['parent_phone'],
                'graduation_payment_date' => $payload['graduation_payment_date'],
                'diploma_biodata_accurate' => $payload['diploma_biodata_accurate'],
                'pdm_submission_date' => $payload['diploma_biodata_accurate'] ? null : ($payload['pdm_submission_date'] ?? null),
                'declaration_accepted_at' => now(), 'declaration_version' => Meet::DECLARATION_VERSION,
                'academic_snapshot' => $snapshot,
                'requirements_version' => 2,
                'status' => 'submitted', 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            if (!$source) $registration->submitted_by = $user->id;
            $registration->lock_version = $source ? $source->lock_version + 1 : 1;
            $registration->save();

            foreach ($documents as $document) {
                // Borrowed verified rows keep their status, notes, metadata and file.
                if ($targeted && $document->source_document_id !== null) continue;
                $registration->documents()->updateOrCreate(['document_type' => $document->document_type], [
                    'file_name' => $document->file_name, 'file_path' => $document->file_path,
                    'file_size' => $document->file_size, 'file_type' => $document->file_type,
                    'status' => 'pending', 'notes' => null,
                ]);
            }
            // Lepaskan FK source_document_id sebelum menghapus row final obsolete.
            $locked->documents()->delete();
            $registration->documents()->whereNotIn('document_type', $types)->delete();
            $retired = array_values(array_diff(array_merge($oldPaths, $allDocuments->pluck('file_path')->all()),
                $documents->pluck('file_path')->all()));
            $registration->logAction($source ? 'resubmitted' : 'submitted', $oldStatus,
                'submitted', $source ? 'Mahasiswa mengirim revisi.' : 'Pendaftaran dikirim mahasiswa.', $user->id);
            $locked->status = SidangTaRegistrationDraft::STATUS_CONSUMED;
            $locked->submitted_registration_id = $registration->id;
            $locked->save(); // Version draft tetap untuk identitas replay.

            return $registration;
        });

        // Defer physical cleanup until any caller's outer transaction commits as well.
        DB::afterCommit(function () use ($retired) {
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
        });
        return $registration;
    }
}
