<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSidangTaExecutionRequest;
use App\Models\AcademicPeriod;
use App\Models\SidangTaPeriod;
use App\Services\SidangTaExecutionWindowService;

class SidangTaExecutionController extends Controller
{
    public function index()
    {
        return view('sidang_ta_registrations.admin.execution-settings', ['periods' => SidangTaPeriod::with('executionSetting')->latest('id')->get(), 'academicPeriods' => AcademicPeriod::latestPeriod()->get()]);
    }

    public function update(SaveSidangTaExecutionRequest $request, SidangTaPeriod $period, SidangTaExecutionWindowService $service)
    {
        $service->save($request->user(), $period, $request->validated(), (int) $request->validated('lock_version'));

        return back()->with('success', 'Jadwal pelaksanaan Sidang TA disimpan.');
    }
}
