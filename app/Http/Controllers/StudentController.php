<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\Student;
use App\Models\AcademicPeriod;
use App\Constants\ProdiList;
use App\Services\MasterDataApiService;
use App\Jobs\ProcessCampusScraping;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StudentController extends Controller
{
    use AuthorizesRequests;
    public function __construct(private MasterDataApiService $masterData) {}

    public function index(Request $request)
    {
        $hasFilter = $request->hasAny(['prodi', 'angkatan', 'search']);

        $students = $hasFilter
            ? Student::query()
                ->when($request->filled('prodi'), fn($q) =>
                    $q->where('study_program', $request->prodi)
                )
                ->when($request->filled('angkatan'), fn($q) =>
                    $q->where('academic_year', $request->angkatan)
                )
                ->when($request->filled('search'), fn($q) =>
                    $q->where(function ($inner) use ($request) {
                        $inner->where('name', 'like', "%{$request->search}%")
                            ->orWhere('student_number', 'like', "%{$request->search}%");
                    })
                )
                ->latest()
                ->paginate(15)
                ->withQueryString()
            : null;

        $angkatanList = Student::select('academic_year') 
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');

        $prodiList = Student::select('study_program')
            ->distinct()
            ->orderBy('study_program')
            ->pluck('study_program');

        return view('students.index', compact('students', 'angkatanList', 'prodiList', 'hasFilter'));
    }

    public function previewCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.mahasiswa_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        $angkatanFromCookies = collect($result['data'])
            ->pluck('tahun_akademik')
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'success'  => true,
            'data'     => $result['data'],
            'total'    => count($result['data']),
            'angkatan' => $angkatanFromCookies,
        ]);
    }

    public function importFromCookies(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
        ]);

        $url    = config('services.master_data.mahasiswa_endpoint');
        $result = $this->masterData->fetchWithCookieString($url, $request->cookie_string);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        \App\Jobs\ProcessStudentSync::dispatch($result['data']);

        $message = "Proses sinkronisasi " . count($result['data']) . " data mahasiswa sedang berjalan di latar belakang (background).";

        return redirect()->route('students.index')->with('success', $message);
    }

    /**
     * Dispatch the campus portal scraping job from the dashboard UI.
     */
    public function scrapePortal(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'min:10'],
            'prodi_id'      => ['nullable', 'integer'], // ID Prodi iGracias
        ]);

        $testUrl = config('igracias.endpoints.student_list');
        $testParams = [
            'act' => 'viewMasterStudent', 'sEcho' => 1, 'iColumns' => 24, 'sColumns' => '',
            'iDisplayStart' => 0, 'iDisplayLength' => 1,
            'sSearch' => '', 'bRegex' => 'false',
            'iSortCol_0' => 0, 'sSortDir_0' => 'asc', 'iSortingCols' => 1,
            'studentType' => 1, 'generation' => 1819, 'studyProgram' => $request->prodi_id ?: 113,
            'semester' => '', 'provinceid' => '', 'suku' => '', 'class1' => '', 'class2' => '', 'class3' => '',
        ];

        $pageid = config('igracias.pageid');
        $referer = 'https://igracias.telkomuniversity.ac.id/';
        if (!empty($pageid)) {
            $referer .= '?pageid=' . $pageid;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Cookie'           => $request->cookie_string,
                    'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Accept'           => 'application/json, text/html',
                    'Referer'          => $referer,
                ])
                ->timeout(15)
                ->get($testUrl, $testParams);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('scrapePortal: Pre-check request failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Gagal menghubungi server iGracias. Periksa koneksi internet dan coba lagi.');
        }

        $body      = strtolower($response->body());
        $status    = $response->status();
        $isExpired = false;
        $isPermissionDenied = false;

        \Illuminate\Support\Facades\Log::info('scrapePortal: Pre-check response', [
            'http_status'  => $status,
            'body_length'  => strlen($response->body()),
            'body_preview' => substr($response->body(), 0, 500),
        ]);

        if (str_contains($body, 'you_dont_have_permission')) {
            $controlProdi = 113;
            $controlTestParams = array_merge($testParams, ['studyProgram' => $controlProdi]);
            try {
                $controlResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->withHeaders([
                        'Cookie'           => $request->cookie_string,
                        'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Accept'           => 'application/json, text/html',
                        'Referer'          => $referer,
                    ])
                    ->timeout(15)
                    ->get($testUrl, $controlTestParams);
                
                $controlBody = strtolower($controlResponse->body());
                $controlJson = $controlResponse->json();
                
                if (str_contains($controlBody, 'you_dont_have_permission')) {
                    $isExpired = true;
                } elseif (is_array($controlJson) && (isset($controlJson['aaData']) || isset($controlJson['iTotalDisplayRecords']))) {
                    $isPermissionDenied = true;
                } else {
                    $isExpired = true;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('scrapePortal: Pre-check kontrol request gagal', ['error' => $e->getMessage()]);
                $isPermissionDenied = true;
            }
        }

        if (!$isPermissionDenied) {
            if (in_array($status, [401, 403]) || ($status >= 300 && $status < 400)) {
                $isExpired = true;
            } else {
                $indicators = [
                    'id="loginform"', "id='loginform'", 'name="textpassword"', "name='textpassword'",
                    'sso username', 'session expired', 'sesi anda telah berakhir',
                    'redirect_to_login', 'APPLICATION_ID__',
                ];
                foreach ($indicators as $ind) {
                    if (str_contains($body, strtolower($ind))) {
                        $isExpired = true;
                        break;
                    }
                }
            }

            if (!$isExpired) {
                $json = $response->json();
                if (!is_array($json) || !isset($json['aaData'])) {
                    if (is_array($json) && isset($json['iTotalDisplayRecords'])) {
                        \Illuminate\Support\Facades\Log::info('scrapePortal: Pre-check valid DataTables response (empty data, but session OK)');
                    } else {
                        $isExpired = true;
                    }
                }
            }
        }

        if ($isExpired) {
            return back()->with('error',
                'Gagal: Cookie String sudah expired atau tidak valid. Silakan login ulang ke iGracias.'
            );
        }

        if (\Illuminate\Support\Facades\Cache::has('scraping-job-running')) {
            return back()->with('error',
                'Scraping job sedang berjalan! Tunggu job sebelumnya selesai sebelum menjalankan yang baru.'
            );
        }

        $prodiFilter = $request->prodi_id ? [(int) $request->prodi_id] : null;
        $startPhase  = (int) ($request->start_phase ?? 1);
        ProcessCampusScraping::dispatch($request->cookie_string, $prodiFilter, $startPhase);

        $totalStudents = Student::count();
        $belumScrape = Student::whereNull('gpa')->count();
        $message = "Scraping job dispatched! Mengambil data mahasiswa di background. "
                 . "({$belumScrape} profil mahasiswa menunggu diperbarui dari total {$totalStudents} di database). "
                 . "Pastikan queue worker berjalan (php artisan queue:work).";

        return redirect()->route('students.index')->with('success', $message);
    }

    public function create()
    {
        return view('students.create');
    }

    public function store(StoreStudentRequest $request)
    {
        Student::create($request->validated());
        return redirect()->route('students.index')
            ->with('success', 'Mahasiswa berhasil ditambahkan.');
    }

    public function edit(Student $student)
    {
        return view('students.edit', compact('student'));
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $data = $request->validated();

        if ($request->hasFile('profile_photo')) {
            if ($student->profile_photo) {
                Storage::disk('public')->delete($student->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')
                ->store('foto-mahasiswa', 'public');
        } else {
            unset($data['profile_photo']);
        }

        $student->update($data);

        return redirect()->route('students.index')
            ->with('success', "Data {$student->name} berhasil diperbarui.");
    }

    public function destroy(Student $student)
    {
        if ($student->profile_photo) {
            Storage::disk('public')->delete($student->profile_photo);
        }
        $student->delete();
        return redirect()->route('students.index')
            ->with('success', 'Data mahasiswa berhasil dihapus.');
    }

    public function show(Student $student)
    {
        $this->authorize('view', $student);

        $student->load([
            'courses' => fn($q) => $q->orderBy('semester')->orderBy('course_name'),
            'courses.academicPeriod',
            'grades'  => fn($q) => $q->orderBy('level')->orderBy('course_name'),
            'attendances' => fn($q) => $q->orderBy('course_name'),
            'activities'  => fn($q) => $q->orderBy('date', 'desc'),
            'eprts'       => fn($q) => $q->orderBy('total_score', 'desc'),
            // Relasi profil akademik (iGracias + BASILA)
            'finalProject',
            'tak',
            'skpi',
            'sidangTingkat',
            'basilaApproval',
            'finalProjectGrade',
            'courseIssues',
            'graduationPayment',
            'bppPayment',
        ]);

        $nilaiPerTingkat = $student->grades
            ->groupBy('level')
            ->sortKeys();

        $detail = app(\App\Services\StudentAcademicDetail::class)->forStudent($student);
        $totalTak = $detail['tak_score'];

        // KRS aktif mengikuti periode akademik yang dipilih admin.
        // `semester` tetap merupakan semester studi mahasiswa (mis. 8),
        // bukan ganjil/genap pada periode akademik.
        $activeAcademicPeriod = AcademicPeriod::current();
        $krsAktif = $activeAcademicPeriod
            ? $student->courses
                ->where('academic_period_id', $activeAcademicPeriod->id)
                ->values()
            : collect();
        $krsLabel = $activeAcademicPeriod?->label;
        $krsStudySemester = $krsAktif->pluck('semester')->filter()->unique()->sort()->implode(', ');

        // Ambil nilai untuk mata kuliah di KRS aktif (untuk cek ada/tidaknya nilai)
        $krsCodes = $krsAktif->pluck('course_code')->toArray();
        $activeCourseGrades = $student->grades->whereIn('course_code', $krsCodes)->values();

        return view('students.show', compact(
            'student',
            'nilaiPerTingkat',
            'totalTak',
            'krsAktif',
            'krsLabel',
            'krsStudySemester',
            'activeAcademicPeriod',
            'activeCourseGrades',
            'detail'
        ));
    }
}
