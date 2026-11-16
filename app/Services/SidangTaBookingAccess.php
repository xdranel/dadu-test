<?php

namespace App\Services;

use App\Models\RoomBookingRequest;
use App\Models\SidangTaRegistration;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SidangTaBookingAccess
{
    public function authorizeStudent(User $actor, SidangTaRegistration $registration, bool $writing = false): void
    {
        abort_unless($actor->can('sidang_ta_registration_read') && (! $writing || $actor->can('sidang_ta_registration_create')), 403);
        try {
            $student = app(SidangTaStudentContext::class)->studentFor($actor);
        } catch (ValidationException) {
            abort(403);
        }
        abort_unless($student->student_number === $registration->student_number, 403);
    }

    public function authorizeStaff(User $actor, ?RoomBookingRequest $booking = null): void
    {
        abort_unless($actor->can('sidang_ta_registration_review'), 403);
        if ($booking && $booking->booking_origin === 'ordinary') {
            abort_unless($actor->hasRole('Akademik') || $actor->can('room_booking_review'), 403);
        }
    }
}
