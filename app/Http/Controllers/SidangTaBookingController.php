<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmSidangTaBookingRequest;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaBookingAccess;
use App\Services\SidangTaBookingIdentity;
use App\Services\SidangTaBookingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class SidangTaBookingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [function (Request $request, Closure $next) {
            abort_unless(config('sidang_ta.student_booking_enabled', false), 404);

            return $next($request);
        }];
    }

    public function show(Request $request, SidangTaRegistration $sidang_ta_registration)
    {
        $registration = $sidang_ta_registration;
        app(SidangTaBookingAccess::class)->authorizeStudent($request->user(), $registration);

        return view('sidang_ta_registrations.booking.index', ['registration' => $registration->load('roomSchedule.booking.room', 'roomSchedule.booking.occurrences', 'period.executionSetting'), 'identity' => app(SidangTaBookingIdentity::class)->forRegistration($registration), 'isLatestApproved' => app(SidangTaBookingService::class)->isLatestApproved($registration), 'preview' => null]);
    }

    public function preview(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaBookingService $service)
    {
        $registration = $sidang_ta_registration;
        $v = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $p = $service->preview($request->user(), $registration, $v['date']);

        return view('sidang_ta_registrations.booking.index', ['registration' => $registration, 'identity' => $p['identity'], 'isLatestApproved' => true, 'preview' => $p]);
    }

    public function confirm(ConfirmSidangTaBookingRequest $request, SidangTaRegistration $sidang_ta_registration, SidangTaBookingService $service)
    {
        $registration = $sidang_ta_registration;
        $service->confirm($request->user(), $registration, $request->validated('preview_token'));

        return redirect()->route('sidang-ta-bookings.index', $registration)->with('success', 'Jadwal sidang dikonfirmasi. Hubungi SSC untuk perubahan.');
    }
}
