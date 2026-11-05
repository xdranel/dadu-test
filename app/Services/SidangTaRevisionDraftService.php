<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SidangTaRevisionDraftService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftAccess $access) {}

    public function start(User $user, SidangTaRegistration $registration): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_update'), 403);
        $student = $this->context->studentFor($user);
        abort_unless($registration->student_number === $student->student_number, 403);

        return DB::transaction(function () use ($user, $registration, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $existing = SidangTaRegistrationDraft::where('source_registration_id', $registration->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->lockForUpdate()->first();
            $source = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->student_number === $student->student_number, 403);
            abort_unless($source->requirements_version === 2, 409,
                'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
            abort_unless($source->status === 'needs_review', 409, 'Pengajuan tidak sedang membutuhkan revisi.');
            SidangTaRevisionDeadline::ensureOpen($source);
            Revision::assertCanRequest($source);
            if ($existing) {
                Requirements::assertKnownVersion($existing);
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                $this->access->check($user, $existing, true);
                return $existing;
            }

            $draft = SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id, 'student_number' => $student->student_number,
                'sidang_ta_period_id' => $source->sidang_ta_period_id,
                'source_registration_id' => $source->id,
                'source_version' => $source->lock_version,
                'requirements_version' => 2,
                'payload' => [
                    'student_phone' => $source->student_phone, 'parent_phone' => $source->parent_phone,
                    'graduation_payment_date' => $source->graduation_payment_date?->toDateString(),
                    'diploma_biodata_accurate' => $source->diploma_biodata_accurate,
                ],
            ]);
            $types = Eligibility::requiredTypes($source);
            foreach ($source->documents()->whereIn('document_type', $types)->get() as $document) {
                $draft->documents()->create([
                    'document_type' => $document->document_type, 'file_name' => $document->file_name,
                    'file_path' => $document->file_path, 'file_size' => $document->file_size,
                    'file_type' => $document->file_type, 'source_document_id' => $document->id,
                ]);
            }
            return $draft;
        });
    }


    public function discard(User $user, SidangTaRegistrationDraft $draft, int $version): void
    {
        $student = $this->access->owner($user, $draft);
        $this->access->assertCanWrite($user, $draft);
        $paths = DB::transaction(function () use ($user, $draft, $version, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            Requirements::assertKnownVersion($locked);
            $this->access->owner($user, $locked);
            $this->access->assertCanWrite($user, $locked);
            abort_unless($locked->status === SidangTaRegistrationDraft::STATUS_ACTIVE
                && $locked->version === $version, 409);
            // Hanya file milik draft, bukan file final yang dipinjam.
            $paths = $locked->documents()->whereNull('source_document_id')->pluck('file_path')->all();
            $locked->delete();
            return $paths;
        });
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('File draft terhapus metadata tetapi perlu cleanup.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup discard gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }
}
