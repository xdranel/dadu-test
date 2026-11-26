<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SidangTaExecutionOutcomeService
{
    public function set(User $actor, SidangTaRegistration $reg, string $status, int $version, ?string $notes = null): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        if (! array_key_exists($status, SidangTaExecutionInfoService::RESULTS) || mb_strlen($notes ?? '') > 2000) {
            throw ValidationException::withMessages(['status' => 'Pilih hasil sidang yang valid; catatan maksimal 2000 karakter.']);
        }

        return $this->record($actor, $reg, $status, $version, $notes);
    }

    public function canRecord(SidangTaRegistration $registration, ?SidangTaSchedule $context): bool
    {
        return ($context?->result_status ?? 'pending') === 'pending' && $this->hasAssignedSchedule($registration, $context);
    }

    /** @param array<string, int> $versions Map of registration UUIDs to schedule versions. */
    public function setMany(User $actor, array $versions, string $status): int
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        if (! in_array($status, ['passed', 'failed'], true)) {
            throw ValidationException::withMessages(['status' => 'Pilih Lulus atau Gagal.']);
        }

        $registrations = SidangTaRegistration::whereIn('public_id', array_keys($versions))->orderBy('id')->get();
        if ($registrations->isEmpty() || $registrations->count() !== count($versions)) {
            throw ValidationException::withMessages(['participants' => 'Daftar peserta berubah. Muat ulang dan pilih peserta kembali.']);
        }

        return DB::transaction(function () use ($registrations, $versions, $actor, $status) {
            foreach ($registrations as $registration) {
                $this->record($actor, $registration, $status, $versions[$registration->getRouteKey()], null, pendingOnly: true);
            }

            return $registrations->count();
        }, 3);
    }

    private function hasAssignedSchedule(SidangTaRegistration $registration, ?SidangTaSchedule $context): bool
    {
        if ($registration->status !== 'approved' || ! in_array($context?->state, ['confirmed', 'completed'], true)) {
            return false;
        }

        $booking = $context->booking;
        if ($booking?->status !== 'approved' || blank($booking->room?->room_code) || $booking->occurrences->count() !== 1) {
            return false;
        }

        $schedule = $booking->occurrences->first();

        return $schedule->usage_date !== null && filled($schedule->start_time) && filled($schedule->end_time)
            && substr($schedule->start_time, 0, 5) < substr($schedule->end_time, 0, 5);
    }

    private function record(User $actor, SidangTaRegistration $reg, string $status, int $version, ?string $notes, bool $pendingOnly = false): SidangTaSchedule
    {
        return app(SidangTaBookingLock::class)->run($reg->id, [], function ($registration, $context) use ($actor, $status, $version, $notes, $pendingOnly) {
            abort_unless(($context->wasRecentlyCreated ? 0 : $context->lock_version) === $version, 409, 'Jadwal berubah. Muat ulang.');
            $from = $context->result_status ?? 'pending';
            if ($pendingOnly && $from !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Hasil salah satu peserta sudah tercatat. Buka halaman Tinjau untuk membatalkannya.']);
            }
            if ($status !== 'pending' && ! $this->hasAssignedSchedule($registration, $context)) {
                throw ValidationException::withMessages(['status' => 'Hasil hanya dapat diisi setelah pengajuan disetujui dan ruang, tanggal, serta jam ditetapkan.']);
            }
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
