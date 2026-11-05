<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\AcademicPeriod;
use App\Services\MasterDataApiService;
use App\Jobs\ProcessCourseScraping;
use App\Jobs\ProcessCourseSync;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private MasterDataApiService $masterData) {}

    public function index(Request $request)
    {
        $activePeriod = AcademicPeriod::current();
        $activeYear = $activePeriod?->year;
        $activeSemester = $activePeriod?->semester;

        // Fallback to latest academic year & semester from KRS if not set or no matching KRS
        if (!StudentCourse::where('academic_year', $activeYear)->where('semester', $activeSemester)->exists()) {
            $latestKrs = StudentCourse::orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
            if ($latestKrs) {
                $activeYear = $latestKrs->academic_year;
                $activeSemester = $latestKrs->semester;
            }
        }

        $studentCourses = StudentCourse::join('students', 'student_courses.student_number', '=', 'students.student_number')
            ->where('student_courses.academic_year', $activeYear)
            ->where('student_courses.semester', $activeSemester)
            ->whereNotNull('students.study_program')
            ->where('students.study_program', '!=', '');

        if ($request->filled('prodi')) {
            $studentCourses->where('students.study_program', 'like', "%{$request->prodi}%");
        }

        if ($request->filled('search')) {
            $courseQuery = Course::query();

            $courseQuery->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('course_code', 'like', "%{$request->search}%");
            });

            $matchingCourseCodes = $courseQuery->pluck('course_code');
            $studentCourses->whereIn('student_courses.course_code', $matchingCourseCodes);
        }

        $items = $studentCourses->select('student_courses.course_code', 'student_courses.course_class', 'students.study_program as study_program')
            ->get();

        $rows = $items->groupBy(function ($item) {
            return $item->course_code . '|' . $item->study_program;
        })
        ->map(function ($groupedItems) {
            $first = $groupedItems->first();
            $kelasUnik = $groupedItems->pluck('course_class')
                ->filter()
                ->unique()
                ->sort()
                ->values();

            return (object)[
                'course_code'   => $first->course_code,
                'study_program' => $first->study_program,
                'kelas_list'    => $kelasUnik,
                'kelas_count'   => $kelasUnik->count(),
                'mhs_count'     => $groupedItems->count(),
            ];
        })
        ->values();

        $courseCodesWithKrs = $rows->pluck('course_code')->unique();

        $courseDetails = Course::with(['coordinators', 'teamLecturers'])
            ->whereIn('course_code', $courseCodesWithKrs)
            ->get()
            ->keyBy('course_code');

        $tableData = $rows->map(function ($row) use ($courseDetails) {
            $course = $courseDetails->get($row->course_code);

            $obj = new \stdClass();
            $obj->id              = $course->id ?? null;
            $obj->course_code     = $row->course_code;
            $obj->name            = $course->name ?? '-';
            $obj->study_program   = trim(str_replace(' - Kampus Surabaya', '', $row->study_program));
            $obj->mhs_count       = $row->mhs_count;
            $obj->enrolled_count  = $row->mhs_count;
            $obj->semester        = $course ? $course->semester : null;
            $obj->credits         = $course->credits ?? '-';
            $obj->learning_method = $course->learning_method ?? '-';
            $obj->status          = $course->status ?? '-';
            $obj->kelas_list      = $row->kelas_list;
            $obj->kelas_count     = $row->kelas_count;
            $obj->class           = $row->kelas_list->implode(', ');
            $obj->type            = $course->type ?? '-';
            $obj->coordinators    = $course ? $course->coordinators : collect();
            $obj->teamLecturers   = $course ? $course->teamLecturers : collect();
            return $obj;
        });

        // Add fallback courses without KRS entries if not filtering by prodi
        if (!$request->filled('prodi')) {
            $fallbackQuery = Course::whereNotIn('course_code', $courseCodesWithKrs);

            if ($request->filled('search')) {
                $fallbackQuery->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('course_code', 'like', "%{$request->search}%");
                });
            }

            $coursesWithoutKrs = $fallbackQuery->with(['coordinators', 'teamLecturers'])
                ->get()
                ->map(function ($c) {
                    $obj = new \stdClass();
                    $obj->id              = $c->id;
                    $obj->course_code     = $c->course_code;
                    $obj->name            = $c->name;
                    $obj->study_program   = '-';
                    $obj->mhs_count       = 0;
                    $obj->enrolled_count  = 0;
                    $obj->semester        = $c->semester;
                    $obj->credits         = $c->credits;
                    $obj->learning_method = $c->learning_method;
                    $obj->status          = $c->status;
                    $obj->kelas_list      = collect([$c->class ?: '-']);
                    $obj->kelas_count     = 1;
                    $obj->class           = $c->class ?: '-';
                    $obj->type            = $c->type;
                    $obj->coordinators    = $c->coordinators;
                    $obj->teamLecturers   = $c->teamLecturers;
                    return $obj;
                });

            $tableData = $tableData->concat($coursesWithoutKrs);
        }

        $tableData = $tableData->sortBy([
            ['course_code', 'asc'],
            ['study_program', 'asc'],
        ])->values();

        $page = $request->get('page', 1);
        $perPage = 20;
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $tableData->forPage($page, $perPage)->values(),
            $tableData->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('courses.index', ['courses' => $paginated]);
    }

    public function previewCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.matakuliah_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'total'   => count($result['data']),
        ]);
    }

    public function importFromCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.matakuliah_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        ProcessCourseSync::dispatch($result['data']);

        return redirect()->route('courses.index')
            ->with('success', "Proses sinkronisasi " . count($result['data']) . " data mata kuliah sedang berjalan di latar belakang (background).");
    }

    /**
     * Dispatch the portal scraping job from the dashboard UI.
     */
    public function scrapePortal(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'min:10'],
        ]);

        $testUrl = 'https://igracias.telkomuniversity.ac.id/';
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->withHeaders(['Cookie' => $request->cookie_string])
            ->timeout(10)
            ->get($testUrl);

        $body = strtolower($response->body());
        $isExpired = false;

        if ($response->status() >= 300) {
            $isExpired = true;
        } else {
            $indicators = ['id="loginform"', "id='loginform'", 'name="textpassword"', "name='textpassword'", 'sso username', 'session expired', 'sesi anda telah berakhir', 'loginform'];
            foreach ($indicators as $ind) {
                if (str_contains($body, strtolower($ind))) {
                    $isExpired = true;
                    break;
                }
            }
        }

        if ($isExpired) {
            return back()->with('error', 'Gagal: Cookie String sudah expired atau tidak valid. Silakan login ke iGracias dan ambil Cookie terbaru.');
        }

        ProcessCourseScraping::dispatch($request->cookie_string);

        $totalCourses = Course::count();
        $message = "Scraping job dispatched! Mengambil data mata kuliah dari portal iGracias di background. "
                 . "(Total data saat ini: {$totalCourses} mata kuliah). "
                 . "Pastikan queue worker berjalan (php artisan queue:work).";

        return redirect()->route('courses.index')->with('success', $message);
    }

    public function export(Request $request)
    {
        $courses = Course::query()
            ->when($request->id, fn($q, $id) => $q->where('id', $id))
            ->when($request->filled('prodi'),    fn($q) => $q->where('study_program', $request->prodi))
            ->when($request->filled('semester'), fn($q) => $q->where('semester', $request->semester))
            ->when($request->filled('status'),   fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'),   fn($q) => $q->where(function ($inner) use ($request) {
                $inner->where('name', 'like', "%{$request->search}%")
                      ->orWhere('course_code', 'like', "%{$request->search}%");
            }))
            ->orderBy('study_program')
            ->orderBy('semester')
            ->orderBy('course_code')
            ->get(['course_code', 'name', 'study_program', 'class', 'semester', 'credits', 'type', 'status']);

        $filename = 'courses-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($courses) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Course Code', 'Course Name', 'Study Program', 'Class', 'Semester', 'Credits', 'Type', 'Status']);
            foreach ($courses as $mk) {
                fputcsv($handle, [
                    $mk->course_code,
                    $mk->name,
                    $mk->study_program,
                    $mk->class,
                    $mk->semester,
                    $mk->credits,
                    $mk->type,
                    $mk->status,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        return view('courses.create');
    }

    public function store(StoreCourseRequest $request)
    {
        Course::create($request->validated());
        return redirect()->route('courses.index')
            ->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function edit(Course $course)
    {
        return view('courses.edit', compact('course'));
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $course->update($request->validated());
        return redirect()->route('courses.index')
            ->with('success', "Data {$course->name} berhasil diperbarui.");
    }

    public function show(Course $course)
    {
        $course->load(['coordinators', 'teamLecturers']);

        $activePeriod = AcademicPeriod::current();
        $activeYear = $activePeriod?->year;
        $activeSemester = $activePeriod?->semester;

        // Fallback to latest academic year & semester from KRS if not set or no matching KRS
        if (!StudentCourse::where('academic_year', $activeYear)->where('semester', $activeSemester)->exists()) {
            $latestKrs = StudentCourse::orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
            if ($latestKrs) {
                $activeYear = $latestKrs->academic_year;
                $activeSemester = $latestKrs->semester;
            }
        }

        $studyPrograms = StudentCourse::join('students', 'student_courses.student_number', '=', 'students.student_number')
            ->where('student_courses.course_code', $course->course_code)
            ->where('student_courses.academic_year', $activeYear)
            ->where('student_courses.semester', $activeSemester)
            ->whereNotNull('students.study_program')
            ->where('students.study_program', '!=', '')
            ->distinct()
            ->pluck('students.study_program');

        if ($studyPrograms->isEmpty()) {
            // Check if there are any other semesters' study programs
            $studyPrograms = StudentCourse::join('students', 'student_courses.student_number', '=', 'students.student_number')
                ->where('student_courses.course_code', $course->course_code)
                ->whereNotNull('students.study_program')
                ->where('students.study_program', '!=', '')
                ->distinct()
                ->pluck('students.study_program');
        }

        $studyPrograms = $studyPrograms->map(function ($sp) {
            return trim(str_replace(' - Kampus Surabaya', '', $sp));
        });

        $studyProgram = $studyPrograms->isEmpty()
            ? (trim(str_replace(' - Kampus Surabaya', '', $course->study_program)) ?: '-')
            : $studyPrograms->implode(', ');

        $classes = StudentCourse::where('course_code', $course->course_code)
            ->where('academic_year', $activeYear)
            ->where('semester', $activeSemester)
            ->whereNotNull('course_class')
            ->where('course_class', '!=', '')
            ->distinct()
            ->pluck('course_class')
            ->sort()
            ->values();

        if ($classes->isEmpty()) {
            $classes = StudentCourse::where('course_code', $course->course_code)
                ->whereNotNull('course_class')
                ->where('course_class', '!=', '')
                ->distinct()
                ->pluck('course_class')
                ->sort()
                ->values();
        }

        $classList = $classes->isEmpty()
            ? ($course->class ?: '-')
            : $classes->implode(', ');

        return view('courses.show', compact('course', 'studyProgram', 'classList'));
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return redirect()->route('courses.index')
            ->with('success', 'Mata kuliah berhasil dihapus.');
    }
}
