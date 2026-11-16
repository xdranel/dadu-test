<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\User;
use App\Support\RoomTimeWindows;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomBookingTransitionService
{
    public function inContext(RoomBookingRequest $booking, Closure $callback): RoomBookingRequest
    {
        $expected = (int) $booking->sidang_ta_registration_id;
        $expectedRoom = (int) $booking->room_id;
        $apply = function () use ($booking, $expected, $expectedRoom, $callback) {
            $locked = RoomBookingRequest::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->sidang_ta_registration_id === $expected && (int) $locked->room_id === $expectedRoom, 409, 'Peminjaman baru ditautkan atau berubah. Muat ulang.');
            $locked->setRelation('occurrences', $locked->occurrences()->orderBy('id')->lockForUpdate()->get());

            return $callback($locked);
        };
        if ($expected) {
            return app(SidangTaBookingLock::class)->run($expected, [$booking->room_id], fn () => $apply());
        }

        return DB::transaction(function () use ($booking, $apply) {
            AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();

            return $apply();
        }, 3);
    }

    public function decide(User $actor, RoomBookingRequest $booking, string $decision, ?string $note): RoomBookingRequest
    {
        abort_unless($actor->hasRole('Akademik') || $actor->can('room_booking_review'), 403);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        return $this->inContext($booking, function ($locked) use ($actor, $decision, $note) {
            if ($locked->sidang_ta_registration_id) {
                app(SidangTaBookingAccess::class)->authorizeStaff($actor, $locked);
            }
            abort_unless($locked->status === 'pending', 422, 'Pengajuan bukan berstatus menunggu persetujuan.');
            if ($decision === 'approved') {
                $service = app(RoomAvailabilityService::class);
                $first = $locked->occurrences->min('usage_date')?->toDateString();
                $last = $locked->occurrences->max('usage_date')?->toDateString();
                if (! $first || ! $last || ! $service->coverage($first, $last)['ready']) {
                    throw ValidationException::withMessages(['availability' => 'Sumber jadwal semester belum lengkap atau occurrence belum tersedia.']);
                }
                $busy = $service->rangeExcept($first, $last, $locked->id);
                foreach ($locked->occurrences as $o) {
                    $date = $o->usage_date->toDateString();
                    foreach ($busy[$date][$locked->room_id] ?? [] as $slot) {
                        if ($slot['start'] < RoomTimeWindows::minutes($o->end_time, true) && $slot['end'] > RoomTimeWindows::minutes($o->start_time)) {
                            throw ValidationException::withMessages(['availability' => 'Jadwal telah berubah dan sekarang bentrok pada '.$date.'.']);
                        }
                    }
                }
            }
            $before = $locked->getRawOriginal();
            $from = $locked->status;
            $locked->fill(['status' => $decision, 'admin_note' => $note])->save();
            $this->record($actor, $locked, $from, $note, $before);

            return $locked;
        });
    }

    public function cancel(User $actor, RoomBookingRequest $booking, string $reason): RoomBookingRequest
    {
        return $this->inContext($booking, function ($locked) use ($actor, $reason) {
            $lecturer = ($actor->isSsoAccount() && $actor->hasRole('Pelaksana')) || (app()->environment(['local', 'testing']) && $actor->isManualAccount() && strtolower($actor->email) === 'krisna@dosen.test' && $actor->hasRole('Pelaksana'));
            $ownerPending = $locked->booking_origin === 'ordinary' && $locked->status === 'pending' && $locked->user_id === $actor->id && $lecturer && $actor->can('room_booking_update');
            if (! $ownerPending) {
                if ($locked->sidang_ta_registration_id) {
                    app(SidangTaBookingAccess::class)->authorizeStaff($actor, $locked);
                } else {
                    abort_unless($actor->hasRole('Akademik') || $actor->can('room_booking_review'), 403);
                }
            }
            abort_unless(in_array($locked->status, ['pending', 'approved'], true), 422, 'Peminjaman sudah tidak aktif.');
            if ($locked->sidang_ta_registration_id && $locked->status === 'approved') {
                $first = $locked->occurrences->sortBy(fn ($o) => $o->usage_date->toDateString().' '.$o->start_time)->first();
                if (! $first || $first->usage_date->toDateString().' '.$first->start_time <= now()->format('Y-m-d H:i:s')) {
                    throw ValidationException::withMessages(['schedule' => 'Pelaksanaan sidang telah dimulai. Jadwal historis dipertahankan.']);
                }
            }
            $before = $locked->getRawOriginal();
            $from = $locked->status;
            $locked->fill(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'admin_note' => $reason])->save();
            $this->record($actor, $locked, $from, $reason, $before);

            return $locked;
        });
    }

    public function record(User $actor, RoomBookingRequest $booking, string $from, ?string $note, array $before): void
    {
        RoomBookingHistory::create(['request_id' => $booking->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $booking->status, 'note' => $note, 'before_data' => $before, 'after_data' => $booking->getRawOriginal() + ['occurrences' => $booking->occurrences()->get()->toArray()]]);
        app(SidangTaBookingLifecycle::class)->afterRoomStatusChanged($booking, $actor, $from, $note);
    }
}
