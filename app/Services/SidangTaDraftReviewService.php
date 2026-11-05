<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SidangTaDraftReviewService
{
    public function __construct(private SidangTaDraftAccess $access) {}

    public function mark(User $user, SidangTaRegistrationDraft $draft, int $version): SidangTaRegistrationDraft
    {
        $student = $this->access->owner($user, $draft);
        return DB::transaction(function () use ($user, $draft, $student, $version) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->access->check($user, $locked, true);
            abort_unless($locked->version === $version, 409, 'Draft berubah. Muat ulang.');
            $first = Requirements::firstIncompleteStep($locked);
            if ($first !== null) {
                throw ValidationException::withMessages(['draft' => "Lengkapi tahap {$first} dahulu."]);
            }
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            if ($locked->source_registration_id === null && !$period->isOpen()) {
                throw ValidationException::withMessages(['draft' => 'Periode tutup. Pilih periode terbuka pada tahap 1.']);
            }
            $locked->reviewed_version = $locked->version;
            $locked->save(); // Version tidak dinaikkan oleh tindakan tinjau.
            return $locked;
        });
    }

    public function preview(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        $student = $this->access->owner($user, $draft);
        return DB::transaction(function () use ($user, $draft, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($locked->source_registration_id !== null) {
                $locked->sourceRegistration()->lockForUpdate()->firstOrFail();
            }
            $this->access->check($user, $locked);
            $locked->load(['period', 'documents']);
            abort_unless($locked->reviewed_version === $locked->version
                && Requirements::firstIncompleteStep($locked) === null, 409,
                'Draft belum ditinjau atau telah berubah. Buka form dan tinjau ulang.');
            return $locked;
        });
    }
}
