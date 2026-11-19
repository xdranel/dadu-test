<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SidangTaExecutionOutcomeService
{
    public function set(User $actor, SidangTaRegistration $reg, string $status, int $version, ?string $notes = null): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        if (! array_key_exists($status, SidangTaExecutionInfoService::RESULTS) || mb_strlen($notes ?? '') > 2000) {
            throw ValidationException::withMessages(['status' => 'Pilih hasil sidang yang valid; catatan maksimal 2000 karakter.']);
        }

        return app(SidangTaBookingLock::class)->run($reg->id, [], function ($registration, $context) use ($actor, $status, $version, $notes) {
            if ($status !== 'pending' && ($registration->status !== 'approved' || ! in_array($context->state, ['confirmed', 'completed'], true) || $context->booking?->status !== 'approved' || $context->booking->occurrences->isEmpty())) {
                throw ValidationException::withMessages(['status' => 'Hasil hanya dapat diisi setelah pengajuan disetujui dan jadwal ditetapkan.']);
            }
            abort_unless(($context->wasRecentlyCreated ? 0 : $context->lock_version) === $version, 409, 'Jadwal berubah. Muat ulang.');
            $from = $context->result_status ?? 'pending';
            if ($from === $status && $context->result_notes === $notes) {
                return $context;
            }
            $context->fill(['result_status' => $status, 'result_notes' => $notes, 'result_by' => $status === 'pending' ? null : $actor->id, 'result_at' => $status === 'pending' ? null : now(), 'lock_version' => $context->lock_version + 1])->save();
            $context->executionLogs()->create(['action' => 'result_updated', 'from_status' => $from, 'to_status' => $status, 'notes' => $notes, 'data' => null, 'performed_by' => $actor->id, 'performed_at' => now()]);
            if (! $context->details_snapshot && $context->was_confirmed) {
                app(SidangTaExecutionInfoService::class)->capture($registration);
            }

            return $context->fresh();
        });
    }
}
