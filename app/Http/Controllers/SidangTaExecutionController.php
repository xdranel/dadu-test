<?php

namespace App\Http\Controllers;

use App\Models\SidangTaRegistration;
use App\Services\SidangTaExecutionInfoService;
use App\Services\SidangTaStudentContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SidangTaExecutionController extends Controller
{
    private function student(Request $request)
    {
        try {
            return app(SidangTaStudentContext::class)->studentFor($request->user());
        } catch (ValidationException $e) {
            abort(403, 'Akun belum terhubung dengan data mahasiswa.');
        }
    }

    public function index(Request $request, SidangTaExecutionInfoService $info)
    {
        $student = $this->student($request);
        $registrations = SidangTaRegistration::with('period', 'roomSchedule.booking.occurrences', 'roomSchedule.booking.room')
            ->where('student_number', $student->student_number)->orderByDesc('submitted_at')->orderByDesc('id')->get();
        $current = $registrations->firstWhere('status', 'approved');
        $occurrences = $current?->roomSchedule?->booking?->occurrences;
        if ($occurrences?->isNotEmpty()) {
            $lastEnd = $occurrences->max(fn ($o) => $o->usage_date->toDateString().' '.$o->end_time);
            if ($lastEnd <= now()->format('Y-m-d H:i:s')) {
                $current = null;
            }
        }
        $currentInfo = $current ? $info->forRegistration($current) : null;
        $history = $registrations->filter(fn ($r) => $r->roomSchedule !== null)
            ->map(fn ($r) => ['registration' => $r, 'info' => $info->forRegistration($r, true)]);

        return view('sidang_ta_execution.student.index', compact('current', 'currentInfo', 'history'));
    }

    public function show(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaExecutionInfoService $service)
    {
        abort_unless($sidang_ta_registration->student_number === $this->student($request)->student_number, 403);
        $info = $service->forRegistration($sidang_ta_registration, true);
        return view('sidang_ta_execution.student.show', compact('info'));
    }
}
