<?php

namespace App\Services;

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\SidangTaRevisionContract as Revision;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Validation\ValidationException;

class SidangTaDraftService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftDocumentService $documents,
                                private SidangTaDraftAccess $access,
    ) {}

    public function owned(User $user, SidangTaRegistrationDraft $draft): SidangTaRegistrationDraft
    {
        Requirements::assertKnownVersion($draft);
        $this->access->check($user, $draft);
        return $draft;
    }

    private function openPeriod(int $id): SidangTaPeriod
    {
        $period = SidangTaPeriod::query()->whereKey($id)
            ->openForRegistration()->lockForUpdate()->first();

        if (!$period) {
            throw ValidationException::withMessages([
                'sidang_ta_period_id' => 'Pilih periode yang masih terbuka.',
            ]);
        }

        return $period;
    }

    public function start(User $user, array $validated): SidangTaRegistrationDraft
    {
        abort_unless($user->can('sidang_ta_registration_create'), 403);
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $validated, $student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_if(SidangTaRegistration::where('student_number', $student->student_number)
                ->whereIn('status', ['submitted', 'needs_review'])->exists(),
                409, 'Anda masih memiliki pengajuan aktif.');

            $existing = SidangTaRegistrationDraft::where('student_number', $student->student_number)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)
                ->whereNull('source_registration_id')->lockForUpdate()->first();

            if ($existing) {
                Requirements::assertKnownVersion($existing);
                abort_unless((string) $existing->owner_user_id === (string) $user->id, 409);
                return $existing; // Retry start tidak menimpa draft; ubah lewat saveStep.
            }

            $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);

            return SidangTaRegistrationDraft::create([
                'owner_user_id' => $user->id,
                'student_number' => $student->student_number,
                'sidang_ta_period_id' => $period->id,
                'requirements_version' => 2,
                'payload' => [
                    'student_phone' => $validated['student_phone'],
                    'parent_phone' => $validated['parent_phone'],
                ],
            ]);
        });
    }

    public function saveStep(User $user, SidangTaRegistrationDraft $draft, int $step,
                             int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        $this->access->assertCanWrite($user, $draft);
        $this->owned($user, $draft);
        if ($step !== 1) {
            return $this->documents->save($user, $draft, $step, $expectedVersion, $validated);
        }
        $student = $this->context->studentFor($user);

        return DB::transaction(function () use ($user, $draft, $expectedVersion, $validated, $student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            Requirements::assertKnownVersion($locked);
            $this->access->check($user, $locked, true);
            abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang terlebih dahulu.');
            if (Revision::isTargeted($locked)) {
                Revision::assertPayloadUnchanged($locked, array_replace($locked->payload ?? [], [
                    'student_phone' => $validated['student_phone'], 'parent_phone' => $validated['parent_phone'],
                ]));
            }
            if ($locked->source_registration_id !== null) {
                if ((int) $validated['sidang_ta_period_id'] !== (int) $locked->sidang_ta_period_id) {
                    throw ValidationException::withMessages([
                        'sidang_ta_period_id' => 'Periode revisi harus mengikuti pengajuan asal.',
                    ]);
                }
                $period = $locked->period; // Boleh tertutup; bukan pilihan periode baru.
            } else {
                $period = $this->openPeriod((int) $validated['sidang_ta_period_id']);
            }

            $locked->payload = array_replace($locked->payload ?? [], [
                'student_phone' => $validated['student_phone'],
                'parent_phone' => $validated['parent_phone'],
            ]);
            $locked->sidang_ta_period_id = $period->id;
            $locked->version++;
            $locked->reviewed_version = null;
            $locked->save();

            return $locked;
        });
    }
}
