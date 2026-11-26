<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSidangTaExecutionRequest;
use App\Models\AcademicPeriod;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Services\SidangTaBookingAdministration;
use App\Services\SidangTaExecutionExport;
use App\Services\SidangTaExecutionInfoService;
use App\Services\SidangTaExecutionOutcomeService;
use App\Services\SidangTaExecutionQuery;
use App\Services\SidangTaExecutionWindowService;
use App\Services\SidangTaHistoryPresenter;
use App\Services\SidangTaPlottingImportService;
use App\Services\SidangTaProgramAccess;
use App\Services\SidangTaStaffScheduling;
use Illuminate\Http\Request;

class SidangTaExecutionController extends Controller
{
    public function index(Request $request, SidangTaExecutionQuery $query, SidangTaExecutionInfoService $info)
    {
        $canReview = $request->user()->can('sidang_ta_registration_review');
        $scopedProgram = $canReview ? null : app(SidangTaProgramAccess::class)->programFor($request->user());
        $scope = $scopedProgram === null ? null : app(SidangTaProgramAccess::class)->matchingPrograms($scopedProgram);
        $filters = $query->filters($request, $scope !== null);
        $counts = $query->counts($filters, $scope);
        $registrations = $query->query($filters, $scope)->with('period', 'roomSchedule.booking.room', 'roomSchedule.booking.occurrences')
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->appends($filters);
        $information = $registrations->getCollection()->mapWithKeys(fn ($r) => [$r->id => $info->forRegistration($r)]);
        $periods = SidangTaPeriod::orderByDesc('id')->get();
        $studyPrograms = $canReview ? SidangTaRegistration::whereNotNull('study_program')->distinct()->orderBy('study_program')->pluck('study_program') : collect();

        return view('sidang_ta_execution.admin.index', compact('canReview', 'filters', 'counts', 'registrations', 'information', 'periods', 'studyPrograms'));
    }

    public function settings()
    {
        return view('sidang_ta_registrations.admin.execution-settings', ['periods' => SidangTaPeriod::with('executionSetting')->latest('id')->get(), 'academicPeriods' => AcademicPeriod::latestPeriod()->get()]);
    }

    public function show(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaExecutionInfoService $service)
    {
        app(SidangTaProgramAccess::class)->authorize($request->user(), $sidang_ta_registration);

        return $this->detail($sidang_ta_registration, $service);
    }

    private function detail(SidangTaRegistration $registration, SidangTaExecutionInfoService $service, ?array $preview = null)
    {
        $registration->load('period.executionSetting', 'roomSchedule.booking.occurrences', 'roomSchedule.booking.room');
        $info = $service->forRegistration($registration);
        $canReview = request()->user()->can('sidang_ta_registration_review');
        $events = app(SidangTaHistoryPresenter::class)->forExecution($registration, staff: $canReview);
        $rooms = $canReview ? Room::bookableClassrooms()->orderBy('room_code')->get() : collect();
        $preferredRoomId = $registration->roomSchedule?->preferred_room_id;
        if ($canReview && $preferredRoomId && ! $rooms->contains('id', $preferredRoomId)) {
            $preferred = Room::where('label', 'LAB')->where('status', 'Dapat Dipakai')->find($preferredRoomId);
            if ($preferred) {
                $rooms->push($preferred);
            }
        }
        $examiners = $canReview
            ? Lecturer::whereNotNull('lecturer_code')->where('lecturer_code', '<>', '')->orderBy('lecturer_code')->pluck('name', 'lecturer_code')
            : collect();

        return view('sidang_ta_execution.admin.show', compact('registration', 'info', 'preview', 'rooms', 'examiners', 'events'));
    }

    public function preview(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaStaffScheduling $service, SidangTaExecutionInfoService $info)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'imported_lab' => ['sometimes', 'boolean'],
        ]);
        $preview = $service->preview($request->user(), $sidang_ta_registration, $data['date'], $data['start_time'], $data['end_time'], (bool) ($data['imported_lab'] ?? false));
        if ($request->expectsJson()) {
            return response()->json($preview);
        }

        return $this->detail($sidang_ta_registration, $info, $preview);
    }

    public function confirm(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaStaffScheduling $service)
    {
        $data = $request->validate(['preview_token' => ['required', 'string']]);
        $service->confirm($request->user(), $sidang_ta_registration, $data['preview_token']);

        return redirect()->route('admin.sidang-ta-execution.show', $sidang_ta_registration)->with('success', 'Jadwal sidang telah ditetapkan.');
    }

    public function plottingRooms(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaStaffScheduling $service)
    {
        $slot = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        return response()->json($service->plottingRooms($request->user(), $sidang_ta_registration, $slot), headers: ['Cache-Control' => 'private, no-store']);
    }

    public function outcome(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaExecutionOutcomeService $service)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,passed,failed'], 'context_version' => ['required', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->set($request->user(), $sidang_ta_registration, $data['status'], (int) $data['context_version'], $data['notes'] ?? null);

        return redirect()->route('admin.sidang-ta-execution.show', $sidang_ta_registration)->with('success', 'Hasil sidang diperbarui.');
    }

    public function plotting(Request $request, SidangTaRegistration $sidang_ta_registration, SidangTaBookingAdministration $service)
    {
        $data = $request->validate([
            'context_version' => ['required', 'integer', 'min:0'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'examiner_1_code' => ['present', 'nullable', 'string', 'max:255'],
            'examiner_2_code' => ['present', 'nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'examiner_1_code.present' => 'Pilih kode Penguji 1 yang terdaftar atau kosongkan pilihannya.',
            'examiner_2_code.present' => 'Pilih kode Penguji 2 yang terdaftar atau kosongkan pilihannya.',
        ]);
        $service->editPlotting($request->user(), $sidang_ta_registration, $data);

        return redirect()->route('admin.sidang-ta-execution.show', $sidang_ta_registration)->with('success', 'Data plotting jadwal disimpan.');
    }

    public function import(Request $request, SidangTaPlottingImportService $service)
    {
        $data = $request->validate(['period' => ['required', 'uuid', 'exists:sidang_ta_periods,public_id'], 'file' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $period = SidangTaPeriod::where('public_id', $data['period'])->firstOrFail();
        $result = $service->import($request->user(), $period, $request->file('file')->getRealPath());

        return redirect()->route('admin.sidang-ta-execution.index', ['period' => $period->getRouteKey()])->with('import_result', $result)
            ->with('success', 'Plotting diperiksa. Ruangan dipesan setelah jadwal ditetapkan.');
    }

    public function export(Request $request, SidangTaExecutionQuery $query, SidangTaExecutionExport $export)
    {
        return $export->download($query->query($query->filters($request))->with('period', 'roomSchedule.booking.room', 'roomSchedule.booking.occurrences')->orderBy('student_number')->get());
    }

    public function plottingTemplate(SidangTaExecutionExport $export)
    {
        return $export->downloadTemplate();
    }

    public function update(SaveSidangTaExecutionRequest $request, SidangTaPeriod $period, SidangTaExecutionWindowService $service)
    {
        $data = $request->validated() + ['break_start_time' => null, 'break_end_time' => null];
        $service->save($request->user(), $period, $data, (int) $data['lock_version']);

        return back()->with('success', 'Jadwal pelaksanaan Sidang TA disimpan.');
    }
}
