<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaBookingAccess;
use App\Services\SidangTaBookingAdministration;
use App\Services\SidangTaBookingIdentity;
use Illuminate\Http\Request;

class SidangTaBookingController extends Controller
{
    public function show(Request $request, SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration;
        app(SidangTaBookingAccess::class)->authorizeStaff($request->user());

        return view('sidang_ta_registrations.admin.booking', ['registration' => $registration->load('roomSchedule.booking.room', 'roomSchedule.booking.occurrences', 'roomSchedule.booking.histories'), 'identity' => app(SidangTaBookingIdentity::class)->forRegistration($registration), 'bookings' => ($request->user()->hasRole('Akademik') || $request->user()->can('room_booking_review')) ? RoomBookingRequest::with('room', 'user', 'occurrences')->where('booking_origin', 'ordinary')->whereNull('sidang_ta_registration_id')->where('type', 'single')->whereIn('status', ['pending', 'approved'])->whereDate('start_date', '>=', today())->latest()->limit(100)->get() : collect(), 'rooms' => Room::where('status', 'Dapat Dipakai')->orderBy('room_code')->get()]);
    }

    public function update(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaBookingAdministration $service)
    {
        $registration = $sidang_ta_registration;
        $request->merge(['action' => $request->route('operation')]);
        $v = $request->validate(['action' => ['required', 'in:mark_lecturer,link,reschedule,cancel'], 'context_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:2000'], 'booking_id' => ['required_if:action,link', 'nullable', 'integer', 'exists:room_booking_requests,id']]);
        $user = $request->user();
        $ver = (int) $v['context_version'];
        $reason = $v['reason'];
        match ($v['action']) {
            'mark_lecturer' => $service->markLecturer($user, $registration, $ver, $reason),'link' => $service->linkLecturerBooking($user, $registration, RoomBookingRequest::findOrFail($v['booking_id']), $ver, $reason),'cancel' => $service->cancel($user, $registration, $ver, $reason),'reschedule' => $service->reschedule($user, $registration, $request->only('date', 'room_id', 'start_time', 'end_time'), $ver, $reason)
        };

        return back()->with('success','Jadwal pelaksanaan diperbarui.');
    }
}
