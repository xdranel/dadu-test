<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCampusScraping;
use App\Jobs\SyncMasterDataJob;
use App\Models\AcademicPeriod;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\Course;
use App\Models\CourseLecturer;
use App\Models\StudentActivity;
use App\Models\StudentAttendance;
use App\Models\StudentBasilaApproval;
use App\Models\StudentBppPayment;
use App\Models\StudentCourse;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentFinalProject;
use App\Models\StudentFinalProjectGrade;
use App\Models\StudentGrade;
use App\Models\StudentSidangTingkat;
use App\Models\StudentTak;
use App\Models\SyncLog;
use App\Models\SyncSetting;
use App\Services\StudentAcademicProfileImportService;
use App\Support\JsonSourceLocations;
use App\Support\SyncImportIssues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function index()
    {
        $settings = SyncSetting::where('is_active', true)
            ->orderBy('urutan')
            ->get();

        $latestLogs = SyncLog::select(['id', 'module', 'status', 'total_records', 'processed_records',
                'skipped_records', 'triggered_by', 'started_at', 'completed_at', 'created_at'])
            ->selectRaw("CASE WHEN (error_message IS NOT NULL AND error_message <> '') OR (issue_details IS NOT NULL AND issue_details <> '[]') OR skipped_records > 0 OR status = 'failed' THEN 1 ELSE 0 END AS has_issues")
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->groupBy('module');

        $dataStats = $this->getDataStats();

        return view('sync.index', compact('settings', 'latestLogs', 'dataStats'));
    }

    public function logDetails(SyncLog $syncLog)
    {
        $issues = $syncLog->issue_details ?? [];
        if ($issues === [] && $syncLog->error_message) {
            $issues = [['nim' => null, 'name' => null, 'record' => null, 'path' => null,
                'line' => null, 'message' => $syncLog->error_message]];
        }
        if ($issues === [] && $syncLog->skipped_records > 0) {
            $issues = [['nim' => null, 'name' => null, 'record' => null, 'path' => null,
                'line' => null, 'message' => $syncLog->skipped_records.' data dilewati; rincian belum tersedia untuk log ini.']];
        }
        if ($issues === [] && $syncLog->status === 'failed') {
            $issues = [['nim' => null, 'name' => null, 'record' => null, 'path' => null,
                'line' => null, 'message' => 'Proses gagal; rincian belum tersedia untuk log ini.']];
        }
        return response()->json(['id' => $syncLog->id, 'issues' => $issues]);
    }

    /**
     * Scrape Portal iGracias.
     */
    public function scrapePortal(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'min:10'],
            'prodi_id'      => ['nullable', 'integer'],
            'start_phase'   => ['nullable', 'integer', 'in:1,2,4,5'],
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

        $pageid  = config('igracias.pageid');
        $referer = 'https://igracias.telkomuniversity.ac.id/';
        if (!empty($pageid)) {
            $referer .= '?pageid=' . $pageid;
        }

        try {
            $response = Http::withoutVerifying()
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
            Log::warning('sync.scrapePortal: Pre-check request failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Gagal menghubungi server iGracias. Periksa koneksi internet dan coba lagi.');
        }

        $body      = strtolower($response->body());
        $status    = $response->status();
        $isExpired = false;
        $isPermissionDenied = false;

        Log::info('sync.scrapePortal: Pre-check response', [
            'http_status'  => $status,
            'body_length'  => strlen($response->body()),
            'body_preview' => substr($response->body(), 0, 500),
        ]);

        if (str_contains($body, 'you_dont_have_permission')) {
            $controlProdi      = 113;
            $controlTestParams = array_merge($testParams, ['studyProgram' => $controlProdi]);
            try {
                $controlResponse = Http::withoutVerifying()
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
                Log::warning('sync.scrapePortal: Kontrol request gagal', ['error' => $e->getMessage()]);
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
                        Log::info('sync.scrapePortal: Valid DataTables response (empty data, session OK)');
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

        if (Cache::has('scraping-job-running')) {
            return back()->with('error',
                'Scraping job sedang berjalan! Tunggu job sebelumnya selesai sebelum menjalankan yang baru.'
            );
        }

        $prodiFilter = $request->prodi_id ? [(int) $request->prodi_id] : null;
        $startPhase  = (int) ($request->start_phase ?? 1);
        ProcessCampusScraping::dispatch($request->cookie_string, $prodiFilter, $startPhase);

        $totalStudents = Student::count();
        $belumScrape    = Student::whereNull('gpa')->count();
        $message = "Scraping job berhasil dijadwalkan! Mengambil data dari portal iGracias di background. "
                 . "({$belumScrape} profil mahasiswa menunggu diperbarui dari total {$totalStudents} di database). "
                 . "Pastikan queue worker berjalan: php artisan queue:work";

        return redirect()->route('sync.index')->with('success', $message);
    }

    /**
     * Dispatch job ke queue
     */
    public function dispatch(Request $request)
    {
        $request->validate([
            'cookie_string' => ['required', 'string', 'max:5000'],
            'modules'       => ['required', 'array', 'min:1'],
            'modules.*'     => ['string', 'exists:sync_settings,module'],
        ]);

        $dispatched = [];

        foreach ($request->modules as $module) {
            $setting = SyncSetting::where('module', $module)
                ->where('is_active', true)
                ->first();

            if (!$setting || empty($setting->endpoint_url)) {
                continue;
            }

            $log = SyncLog::create([
                'module'       => $module,
                'status'       => 'pending',
                'triggered_by' => auth()->user()->email,
            ]);

            SyncMasterDataJob::dispatch(
                $module,
                $request->cookie_string,
                $log->id
            );

            $dispatched[] = $module;
        }

        if (empty($dispatched)) {
            return back()->with('error', 'Tidak ada modul yang dapat disinkronisasi. Pastikan URL endpoint sudah dikonfigurasi.');
        }

        $modules = implode(', ', $dispatched);
        return back()->with('success', "Sinkronisasi dijadwalkan untuk: {$modules}. Proses berjalan di background.");
    }

    public function settings()
    {
        $settings = SyncSetting::orderBy('urutan')->get();
        return view('sync.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'endpoints'          => ['required', 'array'],
            'endpoints.*.url'    => ['nullable', 'url', 'max:500'],
            'endpoints.*.active' => ['nullable', 'boolean'],
        ]);

        foreach ($request->endpoints as $module => $data) {
            SyncSetting::where('module', $module)->update([
                'endpoint_url' => $data['url']    ?? null,
                'is_active'    => isset($data['active']) ? true : false,
            ]);
        }

        return back()->with('success', 'Pengaturan URL endpoint berhasil disimpan.');
    }

    public function status(Request $request)
    {
        $logs = SyncLog::when($request->module, fn($q, $m) => $q->where('module', $m))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get(['id', 'module', 'status', 'total_records',
                   'processed_records', 'error_message', 'completed_at']);

        return response()->json($logs);
    }

    /**
     * Endpoint AJAX
     */
    public function dataStats()
    {
        return response()->json($this->getDataStats());
    }

    public function importJson(Request $request)
    {
        $request->validate([
            'type'      => ['required', 'string', 'in:student,krs,attendance,course,academic_profile'],
            'json_file' => ['required', 'file', 'mimetypes:application/json,text/plain'],
        ]);

        if ($request->type === 'academic_profile') {
            return $this->importAcademicProfile($request);
        }

        if (!$request->hasFile('json_file')) {
            return back()->with('error', 'Silakan unggah file JSON.');
        }

        $jsonString = file_get_contents($request->file('json_file')->getRealPath());



        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return back()->with('error', 'Format JSON tidak valid: ' . json_last_error_msg());
        }

        if ($request->type === 'krs' && \App\Support\OfficialKrsJsonNormalizer::supports($data)) {
            return $this->importOfficialKrs($data, JsonSourceLocations::rows($jsonString));
        }

        $locations = JsonSourceLocations::rows($jsonString,
            isset($data['aaData']) && is_array($data['aaData']) ? 'aaData' : null);

        // Handle case where root JSON contains the DataTables "aaData" key
        if (isset($data['aaData']) && is_array($data['aaData'])) {
            $data = $data['aaData'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            // Handle custom JSON format containing "data" key
            $data = $data['data'];
        }

        // Handle case where root JSON is a single object instead of an array
        if (!empty($data) && !isset($data[0])) {
            $data = [$data];
        }

        $log = SyncLog::create([
            'module'       => $request->type,
            'status'       => 'processing',
            'triggered_by' => auth()->user()->email,
            'started_at'   => now(),
        ]);

        try {
            $processed = 0;
            $skipped   = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                if (!is_array($row)) {
                    $skipped++;
                    $errors[] = "Baris #{$index}: Record harus berupa objek atau daftar JSON.";
                    continue;
                }

                $result = match($request->type) {
                    'student'    => $this->importStudentRow($row),
                    'krs'        => $this->importKrsRow($row),
                    'attendance' => $this->importAttendanceRow($row),
                    'course'     => $this->importCourseRow($row),
                    'graduation' => $this->importGraduationRow($row),
                    default      => false,
                };

                $result ? $processed++ : $skipped++;
                if (!$result) $errors[] = "Baris #{$index}: ".$this->skippedImportReason($request->type, $row);
            }

            if (in_array($request->type, ['krs', 'course'])) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('courses:sync-study-program');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to auto-sync study programs: " . $e->getMessage());
                }
            }

            $log->update([
                'status'            => 'completed',
                'total_records'     => count($data),
                'processed_records' => $processed,
                'skipped_records'   => $skipped,
                'issue_details' => SyncImportIssues::fromErrors($errors, $data, $locations, $request->type),
                'error_message' => SyncImportIssues::summary($errors),
                'completed_at'      => now(),
            ]);

            $moduleLabels = [
                'student'    => 'Profil Mahasiswa',
                'krs'        => 'KRS Mahasiswa',
                'attendance' => 'Jadwal/Kehadiran Mahasiswa',
                'course'     => 'Mata Kuliah',
                'graduation' => 'Data Yudisium (iGracias & BASILA)',
            ];

            $label = $moduleLabels[$request->type] ?? $request->type;

            return redirect()->route('sync.index')
                ->with('success', "Import {$label} selesai: {$processed} data berhasil diimpor, {$skipped} data dilewati.");

        } catch (\Throwable $e) {
            Log::error("ImportJson [{$request->type}]: " . $e->getMessage());

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Penyimpanan data gagal; periksa format dan log aplikasi.';
            $errors[] = isset($index) ? "Baris #{$index}: {$message}" : $message;
            $log->update([
                'status' => 'failed', 'processed_records' => $processed, 'skipped_records' => $skipped,
                'error_message' => SyncImportIssues::summary($errors),
                'issue_details' => SyncImportIssues::fromErrors($errors, $data, $locations, $request->type),
                'completed_at' => now(),
            ]);

            return back()->with('error', 'Terjadi kesalahan saat mengimpor data. Periksa catatan sinkronisasi.');
        }
    }

    private function importOfficialKrs(array $payload, array $locations = [])
    {
        try {
            $rows = (new \App\Support\OfficialKrsJsonNormalizer)->rows($payload);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['json_file' => $exception->getMessage()]);
        }
        $log = SyncLog::create([
            'module' => 'krs', 'status' => 'processing', 'total_records' => count($rows),
            'triggered_by' => auth()->user()->email, 'started_at' => now(),
        ]);
        try {
            $result = app(\App\Services\OfficialKrsJsonImportService::class)->import($rows);
            $skipped = $result['skipped'] + $result['mismatched'];
            $log->update([
                'status' => 'completed', 'processed_records' => $result['processed'],
                'skipped_records' => $skipped,
                'error_message' => SyncImportIssues::summary($result['errors']),
                'issue_details' => SyncImportIssues::fromErrors($result['errors'], $rows, $locations),
                'completed_at' => now(),
            ]);
            $message = "Import KRS resmi selesai: {$result['processed']} mahasiswa diproses, "
                ."{$result['courses']} entri mata kuliah disimpan, {$result['skipped']} dilewati, "
                ."{$result['mismatched']} NIM-nama mismatch, {$result['removed']} KRS lama pada periode terkait dihapus.";
            if ($result['errors'] !== []) $message .= ' Ada catatan pada log sinkronisasi.';
            return redirect()->route('sync.index')->with('success', $message);
        } catch (\Throwable $exception) {
            Log::warning('Official KRS import failed', ['exception' => $exception::class]);
            $log->update(['status' => 'failed', 'error_message' => 'Import KRS resmi gagal.', 'completed_at' => now()]);
            return back()->with('error', 'Import KRS resmi gagal. Periksa log aplikasi.');
        }
    }

    private function skippedImportReason(string $type, array $row): string
    {
        if ($type === 'course') return 'Kode mata kuliah kosong; record dilewati.';
        $nim = $row['nim'] ?? $row['student_number'] ?? ($type === 'krs' ? ($row[0] ?? null) : null);
        if (empty($nim)) return 'NIM kosong; record dilewati.';
        if (in_array($type, ['krs', 'attendance'], true)) {
            $code = $row['kode_matakuliah'] ?? $row['course_code'] ?? null;
            if (empty($code)) return 'Kode mata kuliah kosong; record dilewati.';
            return 'Mahasiswa belum terdaftar; impor profil mahasiswa terlebih dahulu.';
        }
        return 'Record tidak dapat diimpor; periksa nilai wajib.';
    }

    private function importStudentRow(array $row): bool
    {
        $nim = $row['nim'] ?? $row['student_number'] ?? null;
        if (empty($nim)) return false;

        $entryYearSuffix = '';
        if (strlen($nim) === 10) {
            $entryYearSuffix = substr($nim, 4, 2);
        } elseif (strlen($nim) === 12) {
            $entryYearSuffix = substr($nim, 5, 2);
        } else {
            $entryYearSuffix = substr($nim, 4, 2);
        }
        $studentAcademicYear = '20' . $entryYearSuffix;
        if (!is_numeric($studentAcademicYear) || (int)$studentAcademicYear < 2000 || (int)$studentAcademicYear > 2090) {
            $studentAcademicYear = date('Y');
        }

        Student::updateOrCreate(
            ['student_number' => $nim],
            [
                'name'              => $row['nama_siswa']        ?? $row['name']          ?? $row['nama'] ?? '',
                'gender'            => $row['kelamin']           ?? $row['gender']        ?? $row['jenis_kelamin'] ?? null,
                'religion'          => $row['agama']             ?? $row['religion']      ?? null,
                'study_program'     => $row['prodi']             ?? $row['study_program'] ?? $row['program_studi'] ?? '',
                'class_name'        => $row['kelas']             ?? $row['class_name']    ?? null,
                'academic_year'     => $row['academic_year']     ?? $row['tahun_akademik'] ?? (int)$studentAcademicYear,
                'active_semester'   => $row['active_semester']   ?? $row['semester_aktif'] ?? 1,
                'email'             => $row['email']             ?? null,
                'status'            => 'Aktif',
            ]
        );

        return true;
    }

    private function importKrsRow(array $row): bool
    {
        // If it's a flat list array (as in the user's example)
        if (isset($row[0])) {
            $nim = $row[0] ?? null;
            if (empty($nim)) return false;

            // 1. Automatically create or update the student profile
            $gender = $row[3] ?? null;
            if ($gender) {
                $gender = (str_contains(strtoupper($gender), 'WANITA') || str_contains(strtoupper($gender), 'PEREMPUAN') || strtoupper($gender) === 'P')
                    ? 'Perempuan'
                    : 'Laki-laki';
            }

            $religion = $row[4] ?? null;
            if ($religion) {
                $religion = ucfirst(strtolower($religion));
            }

            $studyProgram = $row[5] ?? '';

            $academicYearRaw = $row[10] ?? '';
            $academicYear = $academicYearRaw;
            if (strlen($academicYear) === 4 && is_numeric($academicYear)) {
                $academicYear = '20' . substr($academicYear, 0, 2) . '/20' . substr($academicYear, 2, 2);
            }

            // Extract academic year from NIM using length-based suffix (e.g., 102052400067 -> 2024)
            $entryYearSuffix = '';
            if (strlen($nim) === 10) {
                $entryYearSuffix = substr($nim, 4, 2);
            } elseif (strlen($nim) === 12) {
                $entryYearSuffix = substr($nim, 5, 2);
            } else {
                $entryYearSuffix = substr($nim, 4, 2);
            }
            $studentAcademicYear = '20' . $entryYearSuffix;
            if (!is_numeric($studentAcademicYear) || (int)$studentAcademicYear < 2000 || (int)$studentAcademicYear > 2090) {
                $studentAcademicYear = date('Y');
            }

            $academicPeriodId = $this->resolveKrsAcademicPeriodId($academicYear);

            Student::updateOrCreate(
                ['student_number' => $nim],
                [
                    'name'              => $row[1] ?? '',
                    'gender'            => $gender,
                    'religion'          => $religion,
                    'study_program'     => $studyProgram,
                    'class_name'        => $row[2] ?? null, // Index 2 is Kelas mahasiswa
                    'academic_advisor'  => null, // Clear advisor since it is no longer mapped
                    'academic_year'     => (int)$studentAcademicYear,
                    'active_semester'   => $row[11] ?? 1,
                    'email'             => null,
                    'status'            => 'Aktif',
                ]
            );

            // 2. Create or update the KRS (StudentCourse) record
            $courseCode = $row[7] ?? null;
            if (empty($courseCode)) return true; // student created, but no course code provided

            // Check if course exists in Course master to get credits
            $credits = 3;
            $course = Course::where('course_code', $courseCode)->first();
            if ($course) {
                $credits = $course->credits;
            } else {
                $courseName = strtoupper($row[8] ?? '');
                if (str_contains($courseName, 'AGAMA') || str_contains($courseName, 'RELIGION')) {
                    $credits = 2;
                }

                // Auto-create stub Course agar Foreign Key constraint pada student_courses terpenuhi
                $periodSemester = $academicPeriodId
                    ? AcademicPeriod::find($academicPeriodId)?->semester
                    : null;
                $semesterTerm = $periodSemester === null
                    ? null
                    : ($periodSemester === 2 ? 'GENAP' : 'GANJIL');
                Course::firstOrCreate(
                    [
                        'academic_year' => $academicYear ?: null,
                        'semester_term' => $semesterTerm,
                        'course_code'   => $courseCode,
                    ],
                    [
                        'name'          => $row[8] ?? $courseCode,
                        'study_program' => substr($studyProgram ?: 'Umum', 0, 50),
                        'class'         => substr($row[14] ?? $row[2] ?? '-', 0, 10),
                        'semester'      => (int) ($row[11] ?? 1),
                        'credits'       => $credits,
                        'type'          => 'Wajib',
                        'status'        => 'Aktif',
                    ]
                );

                // Auto-create stub Course agar Foreign Key constraint pada student_courses terpenuhi
                $periodSemester = $academicPeriodId
                    ? AcademicPeriod::find($academicPeriodId)?->semester
                    : null;
                $semesterTerm = $periodSemester === null
                    ? null
                    : ($periodSemester === 2 ? 'GENAP' : 'GANJIL');
                Course::firstOrCreate(
                    [
                        'academic_year' => $academicYear ?: null,
                        'semester_term' => $semesterTerm,
                        'course_code'   => $courseCode,
                    ],
                    [
                        'name'          => $row[8] ?? $courseCode,
                        'study_program' => substr($studyProgram ?: 'Umum', 0, 50),
                        'class'         => substr($row[14] ?? $row[2] ?? '-', 0, 10),
                        'semester'      => (int) ($row[11] ?? 1),
                        'credits'       => $credits,
                        'type'          => 'Wajib',
                        'status'        => 'Aktif',
                    ]
                );
            }

            StudentCourse::updateOrCreate(
                [
                    'student_number'  => $nim,
                    'course_code'     => $courseCode,
                    'academic_year'   => $academicYear,
                    'semester'        => $row[11] ?? 1,
                ],
                [
                    'course_name'     => $row[8]  ?? '',
                    'course_class'    => $row[14] ?? '', // Index 14 is Kelas Mata Kuliah / KRS
                    'lecturer_code'   => $row[9]  ?? null,
                    'lecturer_name'   => null,
                    'credits'         => $credits,
                    'quota'           => null, // Index 12 is prodi ID and index 13 is studentType in this format
                    'study_program'   => $studyProgram,
                    'academic_period_id' => $academicPeriodId,
                ]
            );

            return true;
        }

        // Original logic for associative array
        $nim = $row['nim'] ?? $row['student_number'] ?? null;
        $courseCode = $row['kode_matakuliah'] ?? $row['course_code'] ?? null;
        if (empty($nim) || empty($courseCode)) return false;

        $academicYear = $row['academic_year'] ?? $row['tahun_akademik'] ?? '';
        $academicPeriodId = $this->resolveKrsAcademicPeriodId(
            $academicYear,
            $row['semester_term'] ?? $row['term'] ?? null,
            $row['academic_period_id'] ?? null,
        );

        if (!Student::where('student_number', $nim)->exists()) return false;

        $student = Student::where('student_number', $nim)->first();
        $studyProgram = $row['prodi'] ?? $row['study_program'] ?? $row['program_studi'] ?? ($student ? $student->study_program : null);

        // Auto-create stub Course jika belum ada agar FK tidak gagal
        if (!Course::where('course_code', $courseCode)->exists()) {
            $sem = (int) ($row['semester'] ?? 1);
            $acadYear = $academicYear ?: null;
            $periodSemester = $academicPeriodId
                ? AcademicPeriod::find($academicPeriodId)?->semester
                : null;
            $semesterTerm = $row['semester_term'] ?? ($periodSemester === null
                ? null
                : ($periodSemester === 2 ? 'GENAP' : 'GANJIL'));
            Course::firstOrCreate(
                [
                    'academic_year' => $acadYear,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'name'          => $row['nama_matakuliah'] ?? $row['course_name'] ?? $courseCode,
                    'study_program' => substr($studyProgram ?: 'Umum', 0, 50),
                    'class'         => substr($row['kelas_matakuliah'] ?? $row['course_class'] ?? '-', 0, 10),
                    'semester'      => $sem,
                    'credits'       => (int) ($row['sks'] ?? $row['credits'] ?? 3),
                    'type'          => 'Wajib',
                    'status'        => 'Aktif',
                ]
            );
        }

        // Auto-create stub Course jika belum ada agar FK tidak gagal
        if (!Course::where('course_code', $courseCode)->exists()) {
            $sem = (int) ($row['semester'] ?? 1);
            $acadYear = $row['academic_year'] ?? $row['tahun_akademik'] ?? null;
            $periodSemester = $academicPeriodId
                ? AcademicPeriod::find($academicPeriodId)?->semester
                : null;
            $semesterTerm = $row['semester_term'] ?? ($periodSemester === null
                ? null
                : ($periodSemester === 2 ? 'GENAP' : 'GANJIL'));
            Course::firstOrCreate(
                [
                    'academic_year' => $acadYear,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'name'          => $row['nama_matakuliah'] ?? $row['course_name'] ?? $courseCode,
                    'study_program' => substr($studyProgram ?: 'Umum', 0, 50),
                    'class'         => substr($row['kelas_matakuliah'] ?? $row['course_class'] ?? '-', 0, 10),
                    'semester'      => $sem,
                    'credits'       => (int) ($row['sks'] ?? $row['credits'] ?? 3),
                    'type'          => 'Wajib',
                    'status'        => 'Aktif',
                ]
            );
        }

        StudentCourse::updateOrCreate(
            [
                'student_number'  => $nim,
                'course_code'     => $courseCode,
                'academic_year'   => $academicYear,
                'semester'        => $row['semester']       ?? 1,
            ],
            [
                'course_name'     => $row['nama_matakuliah']  ?? $row['course_name']    ?? '',
                'course_class'    => $row['kelas_matakuliah'] ?? $row['course_class']   ?? '',
                'lecturer_code'   => $row['kode_dosen']       ?? $row['lecturer_code']  ?? null,
                'lecturer_name'   => $row['nama_dosen']       ?? $row['lecturer_name']  ?? null,
                'credits'         => $row['sks']              ?? $row['credits']        ?? 3,
                'study_program'   => $studyProgram,
                'academic_period_id' => $academicPeriodId,
            ]
        );
        return true;
    }

    /**
     * Associate imported KRS rows with the existing academic-period record.
     * The KRS `semester` field is a student's study semester, not the 1/2 term
     * stored by AcademicPeriod, so only an explicit term or current matching
     * academic year can resolve the period.
     */
    private function resolveKrsAcademicPeriodId(?string $academicYear, mixed $term = null, mixed $periodId = null): ?int
    {
        if ($periodId !== null && is_numeric($periodId)) {
            $period = AcademicPeriod::find((int) $periodId);
            if ($period && (!$academicYear || $period->year === $academicYear)) {
                return $period->id;
            }
        }

        if ($academicYear === null || $academicYear === '') {
            return null;
        }

        $query = AcademicPeriod::query();
        $query->where('year', $academicYear);

        if ($term !== null && $term !== '') {
            $normalizedTerm = is_numeric($term)
                ? (int) $term
                : (in_array(strtolower(trim((string) $term)), ['ganjil', 'odd'], true) ? 1
                    : (in_array(strtolower(trim((string) $term)), ['genap', 'even'], true) ? 2 : null));

            if ($normalizedTerm === null || !in_array($normalizedTerm, [1, 2], true)) {
                return null;
            }

            $query->where('semester', $normalizedTerm);
            return $query->value('id');
        }

        $activePeriod = AcademicPeriod::current();
        if ($activePeriod && $activePeriod->year === $academicYear) {
            return $activePeriod->id;
        }

        return null;
    }

    private function importAttendanceRow(array $row): bool
    {
        $nim = $row['nim'] ?? $row['student_number'] ?? null;
        $courseCode = $row['kode_matakuliah'] ?? $row['course_code'] ?? null;
        if (empty($nim) || empty($courseCode)) return false;

        if (!Student::where('student_number', $nim)->exists()) return false;

        if (!Course::where('course_code', $courseCode)->exists()) {
            $sem = (int) ($row['semester'] ?? 1);
            $acadYear = $row['academic_year'] ?? $row['tahun_akademik'] ?? null;
            $semesterTerm = ($sem % 2 === 0) ? 'GENAP' : 'GANJIL';
            Course::firstOrCreate(
                [
                    'academic_year' => $acadYear,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'name'          => $row['nama_matakuliah'] ?? $row['course_name'] ?? $courseCode,
                    'study_program' => substr($row['prodi'] ?? 'Umum', 0, 50),
                    'class'         => substr($row['kelas'] ?? '-', 0, 10),
                    'semester'      => $sem,
                    'credits'       => 3,
                    'type'          => 'Wajib',
                    'status'        => 'Aktif',
                ]
            );
        }

        if (!Course::where('course_code', $courseCode)->exists()) {
            $sem = (int) ($row['semester'] ?? 1);
            $acadYear = $row['academic_year'] ?? $row['tahun_akademik'] ?? null;
            $semesterTerm = ($sem % 2 === 0) ? 'GENAP' : 'GANJIL';
            Course::firstOrCreate(
                [
                    'academic_year' => $acadYear,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'name'          => $row['nama_matakuliah'] ?? $row['course_name'] ?? $courseCode,
                    'study_program' => substr($row['prodi'] ?? 'Umum', 0, 50),
                    'class'         => substr($row['kelas'] ?? '-', 0, 10),
                    'semester'      => $sem,
                    'credits'       => 3,
                    'type'          => 'Wajib',
                    'status'        => 'Aktif',
                ]
            );
        }

        StudentAttendance::updateOrCreate(
            [
                'student_number'  => $nim,
                'course_code'     => $courseCode,
                'academic_year'   => $row['academic_year']  ?? $row['tahun_akademik'] ?? '',
                'semester'        => $row['semester']       ?? 1,
            ],
            [
                'course_name'     => $row['nama_matakuliah'] ?? $row['course_name'] ?? '',
                'class'           => $row['kelas']            ?? $row['class']       ?? null,
                'attended_count'  => $row['jumlah_hadir']     ?? $row['attended_count'] ?? 0,
                'meetings_count'  => $row['jumlah_pertemuan'] ?? $row['meetings_count'] ?? 0,
            ]
        );
        return true;
    }

    private function importCourseRow(array $row): bool
    {
        // ── Format flat array (aaData dari DataTables iGracias) ───────────────
        if (isset($row[0])) {
            $courseCode = $row[1] ?? null;
            if (empty($courseCode)) return false;

            // Index 14 di flat format menyimpan academic_year (contoh: "2425" → "2024/2025")
            $academicYearRaw = $row[14] ?? '';
            $academicYear    = $academicYearRaw;
            if (strlen($academicYear) === 4 && is_numeric($academicYear)) {
                $academicYear = '20' . substr($academicYear, 0, 2) . '/20' . substr($academicYear, 2, 2);
            }

            $semesterTerm = $row[13] ?? null;

            // Composite unique key: (academic_year, semester_term, course_code)
            $semesterTerm = $row[13] ?? null;

            // Composite unique key: (academic_year, semester_term, course_code)
            Course::updateOrCreate(
                [
                    'academic_year' => $academicYear ?: null,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'academic_year' => $academicYear ?: null,
                    'semester_term' => $semesterTerm,
                    'course_code'   => $courseCode,
                ],
                [
                    'course_id'       => $row[0]  ?? null,
                    'name'            => $row[2]  ?? '',
                    'study_program'   => 'Umum',
                    'class'           => substr($row[3] ?? '', 0, 10),
                    'semester'        => is_numeric($row[6] ?? null)
                                            ? (int) $row[6]
                                            : (strtoupper($semesterTerm ?? '') === 'GENAP' ? 2 : 1),
                    'semester_term'   => $semesterTerm,

                    'academic_year'   => $academicYear ?: null,
                    'credits'         => is_numeric($row[4] ?? null) ? (int) $row[4] : 3,
                    // enrolled_count TIDAK disimpan — kolom sudah dihapus dari tabel courses.
                    // Gunakan $course->enrolled_count accessor yang menghitung dari student_courses.
                    'status_dosen'    => $row[11] ?? null,
                    'learning_method' => $row[18] ?? null,
                    'type'            => 'Wajib',
                    'status'          => 'Aktif',
                ]
            );

            // Hapus relasi dosen lama sebelum insert ulang
            CourseLecturer::where('course_code', $courseCode)->delete();

            // ── Index ke-12 (koordinator) — SKIP ─────────────────────────────
            // Koordinator selalu dari kampus pusat dan tidak punya data di tabel
            // lecturers kampus, sehingga menyebabkan baris orphan. Tidak disimpan.

            // ── Index ke-14 (dosen pengampu / team member) ───────────────────
            // Catatan: index 14 di beberapa format juga dipakai untuk academic_year,
            // gunakan index yang sesuai dengan struktur JSON sumber kamu.
            // Berdasarkan request: dosen pengampu ada di index ke-14.
            $teamCode = trim($row[14] ?? '');
            if ($teamCode !== '') {
                $teams = array_filter(array_map('trim', explode(',', $teamCode)));
                foreach ($teams as $tc) {
                    if ($tc === '') continue; // skip string kosong
                    if (!Lecturer::where('lecturer_code', $tc)->exists()) continue;
                    CourseLecturer::firstOrCreate([
                        'course_code'   => $courseCode,
                        'lecturer_code' => $tc,
                        'role'          => 'team_member',
                    ]);
                }
            }
            // Jika dosen pengampu kosong (""), tidak ada yang diinsert — aman.

            return true;
        }

        // ── Format associative array ──────────────────────────────────────────
        $courseCode = $row['course_code'] ?? $row['kode_matakuliah'] ?? null;
        if (empty($courseCode)) return false;

        $academicYear = $row['academic_year'] ?? $row['tahun_akademik'] ?? null;
        $semesterTerm = $row['semester_term'] ?? null;
        $semesterTerm = $row['semester_term'] ?? null;

        // Composite unique key: (academic_year, semester_term, course_code)
        // Composite unique key: (academic_year, semester_term, course_code)
        Course::updateOrCreate(
            [
                'academic_year' => $academicYear,
                'semester_term' => $semesterTerm,
                'course_code'   => $courseCode,
            ],
            [
                'academic_year' => $academicYear,
                'semester_term' => $semesterTerm,
                'course_code'   => $courseCode,
            ],
            [
                'course_id'       => $row['course_id']       ?? null,
                'name'            => $row['name']            ?? $row['nama_matakuliah'] ?? '',
                'study_program'   => substr($row['study_program'] ?? $row['prodi'] ?? 'Umum', 0, 50),
                'class'           => substr($row['class']         ?? $row['kelas'] ?? '', 0, 10),
                'semester'        => (int) ($row['semester']      ?? 1),
                'semester_term'   => $semesterTerm,
                'semester_term'   => $semesterTerm,
                'academic_year'   => $academicYear,
                'credits'         => (int) ($row['credits']       ?? $row['sks'] ?? 3),
                // enrolled_count TIDAK disimpan — gunakan accessor dinamis.
                'status_dosen'    => $row['status_dosen']    ?? null,
                'learning_method' => $row['learning_method'] ?? $row['metode_pembelajaran'] ?? null,
                'type'            => $row['type']            ?? 'Wajib',
                'status'          => $row['status']          ?? 'Aktif',
            ]
        );

        // Hapus relasi dosen lama sebelum insert ulang
        CourseLecturer::where('course_code', $courseCode)->delete();

        // ── Koordinator (associative) — SKIP ─────────────────────────────────
        // Sama seperti flat format: koordinator dari kampus pusat tidak disimpan.
        // Blok ini sengaja dihilangkan.

        // ── Dosen pengampu / team member (associative) ────────────────────────
        $teamCode = trim($row['kode_dosen_pengampu'] ?? $row['team_lecturer_code'] ?? $row['kode_anggota_tim_dosen'] ?? '');
        if ($teamCode !== '') {
            $teams = array_filter(array_map('trim', explode(',', $teamCode)));
            foreach ($teams as $tc) {
                if ($tc === '') continue;
                if (!Lecturer::where('lecturer_code', $tc)->exists()) continue;
                CourseLecturer::firstOrCreate([
                    'course_code'   => $courseCode,
                    'lecturer_code' => $tc,
                    'role'          => 'team_member',
                ]);
            }
        }

        return true;
    }

    /**
     * Import data profil akademik gabungan dari JSON iGracias + BASILA.
     * Endpoint terpisah dari importJson() yang menangani student/krs/attendance/course.
     */
    public function importAcademicProfile(Request $request)
    {
        $request->validate([
            'json_file' => ['required', 'file', 'mimetypes:application/json,text/plain'],
        ]);
        $source = file_get_contents($request->file('json_file')->getRealPath());
        $payload = json_decode($source, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            return back()->withErrors(['json_file' => 'Format JSON tidak valid.']);
        }

        $locations = JsonSourceLocations::rows($source, array_key_exists('data', $payload) ? 'data' : null);
        unset($source);
        $wrapped = array_key_exists('data', $payload);
        $data = $wrapped ? $payload['data'] : $payload;
        if (!is_array($data)) return back()->withErrors(['json_file' => 'Data profil harus berupa array.']);
        if (!array_is_list($data)) $data = [$data];
        $context = $wrapped ? array_intersect_key($payload, array_flip(['selected_menus', 'finance_period'])) : [];
        $context = \Illuminate\Support\Facades\Validator::make($context, [
            'selected_menus' => ['sometimes', 'array', 'list'],
            'selected_menus.*' => ['required', 'string'],
            'finance_period' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}\/[12]$/D'],
        ])->validate();

        $log = SyncLog::create([
            'module' => 'academic_profile', 'status' => 'processing',
            'triggered_by' => auth()->user()->email, 'started_at' => now(),
        ]);
        try {
            $result = app(StudentAcademicProfileImportService::class)->import(
                $data, $context['selected_menus'] ?? null, $context['finance_period'] ?? null
            );
            $result['errors'] = SyncImportIssues::withSourceFailures($result['errors'], $data);
            $log->update([
                'status' => 'completed', 'total_records' => count($data),
                'processed_records' => $result['created'] + $result['updated'],
                'skipped_records' => $result['skipped'] + $result['mismatched'],
                'error_message' => SyncImportIssues::summary($result['errors']),
                'issue_details' => SyncImportIssues::fromErrors($result['errors'], $data, $locations),
                'completed_at' => now(),
            ]);
            $message = "Import Profil Akademik selesai: {$result['created']} baru, {$result['updated']} diperbarui, "
                ."{$result['skipped']} dilewati, {$result['mismatched']} NIM-nama mismatch.";
            if ($result['errors'] !== []) $message .= ' Ada catatan pada log sinkronisasi.';
            return redirect()->route('sync.index')->with('success', $message);
        } catch (\Throwable $exception) {
            Log::warning('ImportAcademicProfile failed', ['exception' => $exception::class]);
            $log->update(['status' => 'failed', 'error_message' => 'Import profil akademik gagal.', 'completed_at' => now()]);
            return back()->with('error', 'Import profil akademik gagal. Periksa format dan log aplikasi.');
        }
    }

    private function importGraduationRow(array $row): bool
    {
        $nim = $row['nim'] ?? $row['student_number'] ?? null;
        if (empty($nim)) return false;

        $failedCourses = $row['failed_courses'] ?? $row['mk_belum_lulus'] ?? null;
        if (is_string($failedCourses)) {
            $failedCourses = json_decode($failedCourses, true) ?: [];
        }

        $retakenCourses = $row['retaken_courses'] ?? $row['mk_mengulang'] ?? null;
        if (is_string($retakenCourses)) {
            $retakenCourses = json_decode($retakenCourses, true) ?: [];
        }

        $photoStatus = $row['ibasila_photo_approval_status'] ?? $row['status_foto_ibasila'] ?? 'pending';
        if (!in_array($photoStatus, ['pending', 'approved', 'rejected'])) {
            $photoStatus = 'pending';
        }

        $level3Status = $row['level_3_exam_status'] ?? $row['status_sidang_tingkat_3'] ?? null;
        if (in_array($level3Status, ['passed', 'lulus', '1', 1], true)) {
            $level3Status = 'passed';
        } elseif (in_array($level3Status, ['not_passed', 'belum_lulus', '0', 0], true)) {
            $level3Status = 'not_passed';
        }

        $activePeriod = \App\Models\GraduationPeriod::where('is_active', true)->value('name') ?? (date('Y') . '/' . (date('Y')+1));

        $updateData = array_filter([
            'student_name'                  => $row['student_name'] ?? $row['nama'] ?? null,
            'graduation_period'             => $row['graduation_period'] ?? $row['periode_yudisium'] ?? $activePeriod,
            'study_program'                 => $row['study_program'] ?? $row['prodi'] ?? null,
            'student_email'                 => $row['student_email'] ?? $row['email'] ?? null,
            'academic_advisor_name'         => $row['academic_advisor_name'] ?? $row['dosen_wali_nama'] ?? null,
            'academic_advisor_code'         => $row['academic_advisor_code'] ?? $row['dosen_wali_kode'] ?? null,
            'thesis_title_id'               => $row['thesis_title_id'] ?? $row['judul_ta_id'] ?? null,
            'thesis_title_en'               => $row['thesis_title_en'] ?? $row['judul_ta_en'] ?? null,
            'advisor_1_code'                => $row['advisor_1_code'] ?? $row['pembimbing_1_kode'] ?? null,
            'advisor_1_name'                => $row['advisor_1_name'] ?? $row['pembimbing_1_nama'] ?? null,
            'advisor_2_code'                => $row['advisor_2_code'] ?? $row['pembimbing_2_kode'] ?? null,
            'advisor_2_name'                => $row['advisor_2_name'] ?? $row['pembimbing_2_nama'] ?? null,
            'examiner_1_code'               => $row['examiner_1_code'] ?? $row['penguji_1_kode'] ?? null,
            'examiner_1_name'               => $row['examiner_1_name'] ?? $row['penguji_1_nama'] ?? null,
            'examiner_2_code'               => $row['examiner_2_code'] ?? $row['penguji_2_kode'] ?? null,
            'examiner_2_name'               => $row['examiner_2_name'] ?? $row['penguji_2_nama'] ?? null,
            'examiner_3_code'               => $row['examiner_3_code'] ?? $row['penguji_3_kode'] ?? null,
            'examiner_3_name'               => $row['examiner_3_name'] ?? $row['penguji_3_nama'] ?? null,
            'tak_score'                     => isset($row['tak_score']) ? (int)$row['tak_score'] : (isset($row['total_tak']) ? (int)$row['total_tak'] : null),
            'eprt_score'                    => isset($row['eprt_score']) ? (float)$row['eprt_score'] : (isset($row['skor_eprt']) ? (float)$row['skor_eprt'] : null),
            'gpa'                           => isset($row['gpa']) ? (float)$row['gpa'] : (isset($row['ipk']) ? (float)$row['ipk'] : null),
            'passed_credits'                => isset($row['passed_credits']) ? (int)$row['passed_credits'] : (isset($row['sks_lulus']) ? (int)$row['sks_lulus'] : null),
            'remaining_credits_db'          => isset($row['remaining_credits_db']) ? (int)$row['remaining_credits_db'] : (isset($row['sks_belum_lulus']) ? (int)$row['sks_belum_lulus'] : null),
            'active_semester_db'            => isset($row['active_semester_db']) ? (int)$row['active_semester_db'] : (isset($row['semester_aktif']) ? (int)$row['semester_aktif'] : null),
            'payment_status'                => $row['payment_status'] ?? $row['status_keuangan'] ?? null,
            'thesis_grade_index'            => $row['thesis_grade_index'] ?? $row['indeks_nilai_ta'] ?? null,
            'exam_date'                     => $row['exam_date'] ?? $row['tgl_sidang'] ?? null,
            'ibasila_photo_approval_status' => $photoStatus,
            'level_3_exam_status'           => $level3Status,
            'failed_courses'                => $failedCourses,
            'retaken_courses'               => $retakenCourses,
            'last_synced_at'                => now(),
            'json_imported_at'              => now(),
        ], fn($v) => !is_null($v));

        \App\Models\GraduationRegistration::updateOrCreate(
            ['student_number' => $nim],
            $updateData
        );

        return true;
    }



    /**
     * jumlah record semua entitas yang menjadi target scraping.
     */
    private function getDataStats(): array
    {
        return [
            ['key' => 'student',         'label' => 'Student',         'count' => Student::count()],
            ['key' => 'lecturer',        'label' => 'Lecturer',        'count' => Lecturer::count()],
            ['key' => 'course',          'label' => 'Course',          'count' => Course::count()],
            ['key' => 'krs',             'label' => 'KRS',             'count' => StudentCourse::count()],
            ['key' => 'nilai',           'label' => 'Nilai',           'count' => StudentGrade::count()],
            ['key' => 'kehadiran',       'label' => 'Kehadiran',       'count' => StudentAttendance::count()],
            ['key' => 'eprt',            'label' => 'EPRT',            'count' => StudentEprt::count()],
            ['key' => 'tak',             'label' => 'TAK',             'count' => StudentActivity::count()],
            ['key' => 'final_project',   'label' => 'Tugas Akhir',     'count' => StudentFinalProject::count()],
            ['key' => 'sidang_tingkat',  'label' => 'Sidang Tingkat',   'count' => StudentSidangTingkat::count()],
            ['key' => 'basila',          'label' => 'BASILA',          'count' => StudentBasilaApproval::count()],
            ['key' => 'ta_grade',        'label' => 'Nilai TA',        'count' => StudentFinalProjectGrade::count()],
            ['key' => 'course_issues',   'label' => 'MK Issue',        'count' => StudentCourseIssue::count()],
            ['key' => 'bpp',             'label' => 'BPP',             'count' => StudentBppPayment::count()],
        ];
    }
}
