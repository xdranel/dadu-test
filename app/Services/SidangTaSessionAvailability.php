<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use Illuminate\Support\Collection;

class SidangTaSessionAvailability
{
    public function eligibleRooms(string $taType): Collection
    {
        $labels = $taType === 'Kelompok' ? [Room::LABEL_CLASSROOM, 'LAB'] : [Room::LABEL_CLASSROOM];

        return Room::whereIn('label', $labels)->where('status', 'Dapat Dipakai')->get()->toBase();
    }

    public function freeRooms(Collection $rooms, SidangTaRegistration $registration, string $taType, array $slot, ?RoomBookingRequest $booking = null): Collection
    {
        if ($rooms->isEmpty()) {
            return $rooms;
        }

        $excluded = $booking && in_array($booking->status, ['pending', 'approved'], true) ? [$booking->id] : [];
        if ($taType === 'Kelompok' && ($excluded === [] || $booking->booking_origin === 'sidang_ta') && filled($registration->study_program)) {
            $shared = SidangTaSchedule::query()->with('registration:id,study_program', 'booking:id,sidang_ta_registration_id', 'booking.occurrences:id,request_id')
                ->whereIn('state', ['confirmed', 'completed'])
                ->whereHas('registration', fn ($query) => $query->where('sidang_ta_period_id', $registration->sidang_ta_period_id)
                    ->where('study_program', $registration->study_program)->where('status', 'approved'))
                ->whereHas('booking', fn ($query) => $query->where('booking_origin', 'sidang_ta')->where('type', 'single')->where('status', 'approved')
                    ->whereIn('room_id', $rooms->pluck('id'))
                    ->whereHas('occurrences', fn ($occurrences) => $occurrences->whereDate('usage_date', $slot['date'])
                        ->whereTime('start_time', $slot['start_time'].':00')->whereTime('end_time', $slot['end_time'].':00')))
                ->get()
                ->filter(fn (SidangTaSchedule $peer) => $peer->taType() === 'Kelompok'
                    && $peer->registration->study_program === $registration->study_program
                    && $peer->booking->sidang_ta_registration_id === $peer->sidang_ta_registration_id
                    && $peer->booking->occurrences->count() === 1)
                ->pluck('room_booking_request_id');
            $excluded = $shared->merge($excluded)->unique()->all();
        }

        return app(RoomAvailabilityService::class)->freeRooms($rooms, $slot['date'], $slot['start_time'], $slot['end_time'], $excluded);
    }
}
