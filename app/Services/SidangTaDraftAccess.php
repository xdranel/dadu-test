<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;

class SidangTaDraftAccess
{
    public function __construct(private SidangTaStudentContext $context) {}

    public function owner(User $user, SidangTaRegistrationDraft $draft): Student
    {
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        abort_unless($draft->student_number === $student->student_number, 403);
        return $student;
    }

    public function assertCanWrite(User $user, SidangTaRegistrationDraft $draft): void
    {
        $permission = $draft->source_registration_id === null
            ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
        abort_unless($user->can($permission), 403);
    }

    public function check(User $user, SidangTaRegistrationDraft $draft, bool $write = false): void
    {
        $this->owner($user, $draft);
        \App\Support\SidangTaDocumentRequirements::assertKnownVersion($draft);
        abort_unless($draft->status === SidangTaRegistrationDraft::STATUS_ACTIVE, 409);
        if ($write) {
            $this->assertCanWrite($user, $draft);
        }
        if ($draft->source_registration_id === null) {
            return;
        }

        // write=true hanya dipanggil di dalam transaksi setelah lock Student/draft.
        $query = SidangTaRegistration::whereKey($draft->source_registration_id);
        $source = ($write ? $query->lockForUpdate() : $query)->firstOrFail();
        abort_unless($source->student_number === $draft->student_number, 403);
        abort_unless($draft->requirements_version === $source->requirements_version, 409,
            'Versi persyaratan draft tidak cocok dengan pengajuan asal. Muat ulang pengajuan.');
        abort_unless($source->status === 'needs_review'
            && $source->lock_version === $draft->source_version, 409,
            'Pengajuan berubah. Buang draft revisi lama lalu muat ulang pengajuan.');

        if ($write) \App\Support\SidangTaRevisionDeadline::ensureOpen($source);

        foreach ($draft->documents()->whereNotNull('source_document_id')->get() as $document) {
            $valid = $source->documents()->whereKey($document->source_document_id)
                ->where('document_type', $document->document_type)
                ->where('file_path', $document->file_path)->exists();
            abort_unless($valid, 409, 'Referensi dokumen pengajuan sudah berubah.');
        }
    }
}
