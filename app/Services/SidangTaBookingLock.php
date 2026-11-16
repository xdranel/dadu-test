<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;

class SidangTaBookingLock
{
    public function run(int $registrationId, array $additionalRoomIds, Closure $callback): mixed
    {
        // Resolve immutable identity before BEGIN: an early nonlocking read would freeze
        // an obsolete repeatable-read snapshot before waiting for the shared locks.
        $initial = SidangTaRegistration::findOrFail($registrationId);

        return DB::transaction(function () use ($registrationId, $callback, $initial) {
            Student::where('student_number', $initial->student_number)->lockForUpdate()->first();
            $registrations = SidangTaRegistration::where('student_number', $initial->student_number)->orderBy('id')->lockForUpdate()->get();
            $reg = $registrations->firstWhere('id', $registrationId);
            abort_unless($reg, 409, 'Pengajuan berubah. Muat ulang.');
            $period = $reg->period()->lockForUpdate()->firstOrFail();
            $reg->setRelation('period', $period);
            $setting = $period->executionSetting()->lockForUpdate()->first();
            $period->setRelation('executionSetting', $setting);
            $context = SidangTaSchedule::where('sidang_ta_registration_id', $reg->id)->lockForUpdate()->first();
            if (! $context) {
                $context = SidangTaSchedule::create(['sidang_ta_registration_id' => $reg->id, 'student_number' => $reg->student_number]);
            }
            AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            // Shared room order also protects global first-fit selection from concurrent allocations.
            Room::orderBy('id')->lockForUpdate()->get();
            $booking = $context->booking()->lockForUpdate()->first();
            if ($booking) {
                $booking->setRelation('occurrences', $booking->occurrences()->orderBy('id')->lockForUpdate()->get());
            }
            $context->setRelation('booking', $booking);

            return $callback($reg, $context, $setting);
        }, 3);
    }
}
