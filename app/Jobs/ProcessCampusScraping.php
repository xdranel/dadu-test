<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  ProcessCampusScraping — 5 Phase Job                                    │
 * │──────────────────────────────────────────────────────────────────────────│
 * │  Phase 1: Daftar Mahasiswa  → ajax.student.php                         │
 * │  Phase 2: Data Profil       → ajax.perwalian.php?act=viewdata          │
 * │  Phase 3: Nilai & SKS       → ajax.perwalian.php?act=viewstudyplan     │
 * │  Phase 4: Data EPRT         → languagecenter.ajax.languagetest.data    │
 * │  Phase 5: Data KRS          → ajax.reg.php?act=viewStudentCourse       │
 * │                                                                        │
 * │  Semua parameter diatur via constant. UI hanya input cookie.           │
 * └──────────────────────────────────────────────────────────────────────────┘
 */
class ProcessCampusScraping implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 14400; // 4 jam (5 phase + delay)

    //  CONFIGURATION
    private const MAX_STUDENTS   = 500;
    private const PAGE_SIZE      = 200;

    private const PERMISSION_DENIED_THRESHOLD = 10;

    //  FILTER: Phase 1 - Daftar Mahasiswa
    private const STUDENT_TYPES  = [1, 2, 3, 5, 6, 8, 10];
    private const GENERATIONS    = [1819, 1920, 2021, 2122, 2223, 2324, 2425, 2526];
    private const STUDY_PROGRAMS = [113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123];

    //  FILTER: Phase 5 - KRS
    private const KRS_SEMESTERS      = [1, 2];
    private const KRS_SCHOOL_YEARS   = [1819, 1920, 2021, 2122, 2223, 2324, 2425, 2526]; 
    private const KRS_STUDY_PROGRAMS = [113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123];

    public function __construct(
        private string $cookieString,
        private ?array $prodiIds = null,
        private int $startPhase = 1,
    ) {
        $this->cookieString = $this->sanitizeCookie($cookieString);
    }

    /**
     * Unique ID — prevents duplicate jobs from being dispatched.
     * Only one scraping job can exist in the queue at a time.
     */
    public function uniqueId(): string
    {
        return 'campus-scraping';
    }

    /**
     * How long the unique lock is held (seconds).
     * After 4 hours the lock auto-releases even if the job crashed.
     */
    public int $uniqueFor = 14400;

    //  MAIN
    public function handle(): void
    {
        // Runtime lock: extra safety in case ShouldBeUnique doesn't cover all edge cases
        $lock = Cache::lock('scraping-job-running', 14400);
        if (!$lock->get()) {
            Log::warning('ProcessCampusScraping: ⚠ Another scraping job is already running. Skipping this dispatch.');
            return;
        }

        try {
            $this->executeAllPhases();
        } finally {
            $lock->release();
        }
    }

    private function executeAllPhases(): void
    {
        Log::info("ProcessCampusScraping: ▶ Job started (starting from phase {$this->startPhase}).");

        //  Phase 1: Fetch daftar mahasiswa
        if ($this->startPhase <= 1) {
            Log::info('ProcessCampusScraping: ═══ Phase 1: Fetch daftar mahasiswa ═══');
            $nims = $this->fetchStudentIdsFromApi();
            if ($nims === null) return;

            if (empty($nims)) {
                Log::warning('ProcessCampusScraping: Tidak ada NIM ditemukan. Aborting.');
                return;
            }

            $nims  = array_slice(array_unique($nims), 0, self::MAX_STUDENTS);
            $total = count($nims);
            Log::info("ProcessCampusScraping: Phase 1 done. {$total} NIM untuk Phase 2&3.");
        } else {
            Log::info("ProcessCampusScraping: ⏭ Skipping Phase 1 (start_phase={$this->startPhase}).");
        }

        //  Phase 2 & 3: Profil + Nilai per mahasiswa
        if ($this->startPhase <= 2) {
            if ($this->startPhase > 1) {
                $query = Student::query();
                if ($this->prodiIds) {
                    $prodiNames = \App\Constants\IgraciasProdi::getNamesByIds($this->prodiIds);
                    if (!empty($prodiNames)) {
                        $query->where(function ($q) use ($prodiNames) {
                            foreach ($prodiNames as $name) {
                                $q->orWhere('study_program', 'like', "%{$name}%");
                            }
                        });
                    }
                }
                $nims = $query->pluck('student_number')->toArray();
                $nims = array_slice($nims, 0, self::MAX_STUDENTS);
                $total = count($nims);
            }

            Log::info('ProcessCampusScraping: ═══ Phase 2&3: Profil + Nilai ═══');
            $processed = 0;
            $failed    = 0;

            foreach ($nims as $i => $nim) {
                $pos = $i + 1;
                try {
                    $profileHtml = $this->fetchPerwalian('viewdata', $nim);
                    if ($profileHtml === null) return;
                    $profileData = $this->parseProfileData($profileHtml, $nim);

                    sleep(rand(2, 5)); // Humanize delay

                    $gradesHtml = $this->fetchPerwalian('viewstudyplan', $nim);
                    if ($gradesHtml === null) return;
                    $gradesData = $this->parseGradesData($gradesHtml);

                    $this->upsertStudent($nim, $profileData, $gradesData);
                    $processed++;
                    Log::info("ProcessCampusScraping: [{$pos}/{$total}] ✅ {$nim}");
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("ProcessCampusScraping: [{$pos}/{$total}] ❌ {$nim}: {$e->getMessage()}");
                }

                if ($i < $total - 1) sleep(rand(2, 5)); // Humanize delay
            }

            Log::info("ProcessCampusScraping: Phase 2&3 done.", compact('processed', 'failed'));
        } else {
            Log::info("ProcessCampusScraping: Skipping Phase 2&3 (start_phase={$this->startPhase}).");
        }

        //  Phase 4: EPRT
        if ($this->startPhase <= 4) {
            Log::info('ProcessCampusScraping: ═══ Phase 4: Data EPRT ═══');
            $this->scrapeEprt();
            sleep(rand(2, 5)); // Humanize delay
        } else {
            Log::info("ProcessCampusScraping: Skipping Phase 4 (start_phase={$this->startPhase}).");
        }

        //  Phase 5: KRS
        if ($this->startPhase <= 5) {
            Log::info('ProcessCampusScraping: ═══ Phase 5: Data KRS ═══');
            $this->scrapeKrs();
        }

        Log::info('ProcessCampusScraping: All phases completed.');
    }

    private function fetchStudentIdsFromApi(): ?array
    {
        $allNims   = [];
        $combIndex = 0;
        $consecutivePermissionDenied = 0;
        $hasAnySuccess = false;
        
        $targetProdis = $this->prodiIds ? array_intersect(self::STUDY_PROGRAMS, $this->prodiIds) : self::STUDY_PROGRAMS;
        $totalCombs = count(self::STUDENT_TYPES) * count(self::GENERATIONS) * count($targetProdis);

        Log::info("ProcessCampusScraping: Phase 1 — {$totalCombs} kombinasi akan diproses.");

        foreach (self::STUDENT_TYPES as $type) {
            foreach (self::GENERATIONS as $gen) {
                foreach ($targetProdis as $prodi) {
                    $combIndex++;
                    try {
                        $nims = $this->fetchStudentListPage($type, $gen, $prodi);
                        if ($nims === null) return null; // session expired

                        if ($this->lastRequestWasPermissionDenied) {
                            $consecutivePermissionDenied++;
                        } else {
                            $consecutivePermissionDenied = 0;
                            $hasAnySuccess = true;
                        }

                        if (!$hasAnySuccess && $consecutivePermissionDenied >= self::PERMISSION_DENIED_THRESHOLD) {
                            Log::error("ProcessCampusScraping: 🔴 {$consecutivePermissionDenied} consecutive permission denied — session likely expired.");
                            $this->abortSessionExpired(
                                'fetchStudentList',
                                "All first {$consecutivePermissionDenied} requests returned permission denied",
                                'you_dont_have_permission_to_access_this_page',
                                200
                            );
                            return null;
                        }

                        $allNims = array_merge($allNims, $nims);
                        $uniqueCount = count(array_unique($allNims));

                        if ($combIndex % 50 === 0 || $combIndex === $totalCombs) {
                            Log::info("ProcessCampusScraping: Phase 1 progress [{$combIndex}/{$totalCombs}] — {$uniqueCount} NIM so far.");
                        }
                    } catch (\Exception $e) {
                        Log::warning("ProcessCampusScraping: Phase 1 [{$combIndex}/{$totalCombs}] type={$type},gen={$gen},prodi={$prodi} ❌ {$e->getMessage()}");
                        // Continue with next combination instead of crashing
                        continue;
                    }

                    if ($combIndex < $totalCombs) sleep(rand(1, 3)); // Humanize delay

                    if (count(array_unique($allNims)) >= self::MAX_STUDENTS) {
                        Log::info("ProcessCampusScraping: Limit reached during list fetch.");
                        break 3;
                    }
                }
            }
        }

        Log::info("ProcessCampusScraping: Phase 1 fetch complete. Total unique NIM: " . count(array_unique($allNims)));
        return array_unique($allNims);
    }

    /**
     * Flag set by fetchStudentListPage to indicate whether the last request
     * was a permission denied response. Used by fetchStudentIdsFromApi to
     * track consecutive denials and detect expired sessions.
     */
    private bool $lastRequestWasPermissionDenied = false;

    private function fetchStudentListPage(int $type, int $gen, int $prodi): ?array
    {
        $nims   = [];
        $offset = 0;
        $this->lastRequestWasPermissionDenied = false;

        do {
            $params = $this->buildStudentListParams($type, $gen, $prodi, $offset);
            $url = config('igracias.endpoints.student_list');
            $response = Http::withoutVerifying()
                ->withHeaders($this->httpHeaders())
                ->timeout(60)
                ->retry(3, 2000)
                ->get($url, $params);

            $httpStatus = $response->status();
            $body = $response->body();

            if ($this->isSessionExpired($body, $httpStatus)) {
                $this->abortSessionExpired('fetchStudentList', "type={$type},gen={$gen},prodi={$prodi}", $body, $httpStatus);
                return null;
            }

            if ($this->isPermissionDenied($body)) {
                $this->lastRequestWasPermissionDenied = true;
                Log::info("ProcessCampusScraping: Permission denied for type={$type},gen={$gen},prodi={$prodi} — skipping.");
                return [];
            }

            $json  = $response->json();
            $total = $json['iTotalDisplayRecords'] ?? 0;
            $rows  = $json['aaData'] ?? [];
            if (empty($rows)) break;

            foreach ($rows as $row) {
                // [0]=NIM, [1]=Nama, [2]=Kelas, [6]=Prodi, [8]=Status
                $nimRaw = trim(strip_tags((string) ($row[0] ?? '')));
                $nim = preg_replace('/[^0-9]/', '', $nimRaw);
                if (!$nim || strlen($nim) < 8) {
                    $nim = $this->extractNimFromRow($row); // fallback
                }

                if ($nim) {
                    $nims[] = $nim;
                    
                    $statusRaw = trim(strip_tags((string) ($row[8] ?? '')));
                    $status = $this->mapStatus($statusRaw);
                    
                    Student::updateOrCreate(
                        ['student_number' => $nim],
                        [
                            'name'            => trim(strip_tags((string) ($row[1] ?? ''))),
                            'class_name'      => trim(strip_tags((string) ($row[2] ?? ''))) ?: null,
                            'study_program'   => trim(strip_tags((string) ($row[6] ?? ''))) ?: '',
                            'academic_year'   => $gen,
                            'status'          => $status,
                            'email'           => trim(strip_tags((string) ($row[14] ?? ''))) ?: null,
                            'phone_number'    => trim(strip_tags((string) ($row[12] ?? ''))) ?: null,
                            'gender'          => trim(strip_tags((string) ($row[17] ?? ''))) ?: null,
                        ]
                    );
                }
            }

            $offset += self::PAGE_SIZE;
            if ($offset < $total) sleep(rand(1, 3)); // Humanize delay
        } while ($offset < $total);

        return $nims;
    }

    private function mapStatus(string $status): string
    {
        $map = [
            'STUDENT'  => 'Aktif',
            'ACTIVE'   => 'Aktif',
            'AKTIF'    => 'Aktif',
            'CUTI'     => 'Cuti',
            'LULUS'    => 'Lulus',
            'DROP OUT' => 'Drop Out',
            'DO'       => 'Drop Out',
            'KELUAR'   => 'Drop Out',
        ];
        return $map[strtoupper(trim($status))] ?? 'Aktif';
    }

    private function extractNimFromRow(array $row): ?string
    {
        foreach ($row as $cell) {
            $clean = trim(strip_tags((string) $cell));
            // Only match 10-15 digits that don't start with 08 (to avoid phone numbers)
            if (preg_match('/^(?!08)\d{10,15}$/', $clean)) return $clean;
        }
        foreach ($row as $cell) {
            if (preg_match('/value=["\']((?!08)\d{10,15})["\']/', (string) $cell, $m)) return $m[1];
        }
        return null;
    }

    private function buildStudentListParams(int $type, int $gen, int $prodi, int $offset): array
    {
        $params = [
            'act' => 'viewMasterStudent', 'sEcho' => 1, 'iColumns' => 24, 'sColumns' => '',
            'iDisplayStart' => $offset, 'iDisplayLength' => self::PAGE_SIZE,
            'sSearch' => '', 'bRegex' => 'false',
            'iSortCol_0' => 0, 'sSortDir_0' => 'asc', 'iSortingCols' => 1,
            'studentType' => $type, 'generation' => $gen, 'studyProgram' => $prodi,
            'semester' => '', 'provinceid' => '', 'suku' => '', 'class1' => '', 'class2' => '', 'class3' => '',
        ];
        for ($i = 0; $i < 24; $i++) {
            $params["mDataProp_{$i}"] = $i;
            $params["sSearch_{$i}"] = '';
            $params["bRegex_{$i}"] = 'false';
            $params["bSearchable_{$i}"] = ($i === 0 || $i === 18) ? 'false' : 'true';
            $params["bSortable_{$i}"]  = ($i === 0 || $i === 18) ? 'false' : 'true';
        }
        return $params;
    }

    //  PHASE 2 & 3: PROFIL + NILAI (ajax.perwalian.php)
    private function fetchPerwalian(string $action, string $studentId): ?string
    {
        $baseUrl = config('igracias.endpoints.perwalian');
        $url = $baseUrl . '?' . http_build_query(['act' => $action, 'studentid' => $studentId]);
        $response = Http::withoutVerifying()
            ->withHeaders($this->httpHeaders())
            ->timeout(60)
            ->retry(3, 2000)
            ->get($url);

        if ($this->isSessionExpired($response->body(), $response->status())) {
            $this->abortSessionExpired($action, $studentId, $response->body(), $response->status());
            return null;
        }

        if ($this->isPermissionDenied($response->body())) {
            Log::info("ProcessCampusScraping: Permission denied for {$action} studentId={$studentId} — skipping.");
            return null;
        }

        return $response->body();
    }

    private function parseProfileData(string $html, string $nim): array
    {
        $crawler = new Crawler($html);
        $fieldMap = [
            'Agama' => 'religion',
            'Dosen Wali' => 'academic_advisor',
            'No HP Ortu' => 'parent_phone_number', 
            'Asal Sekolah' => 'high_school',
            'Lulus TPB' => 'passed_tpb', 
            'Lulus TKII' => 'passed_tkii',
            'Lulus TKIII' => 'passed_tkiii', 
            'Lulus TKIV' => 'passed_tkiv',
        ];
        $result = array_fill_keys(array_values($fieldMap), null);
        $result['profile_photo'] = null;

        // DEBUG: Save sample HTML for first student to inspect structure
        $debugFile = storage_path('logs/sample_profile_' . $nim . '.html');
        if (!file_exists(storage_path('logs/sample_profile_debug_done.flag'))) {
            file_put_contents($debugFile, $html);
            file_put_contents(storage_path('logs/sample_profile_debug_done.flag'), $nim);
            Log::info("ProcessCampusScraping: DEBUG saved sample profile HTML to {$debugFile}");
        }

        // DEBUG: Log all labels found in the HTML
        $allLabels = [];
        $crawler->filter('tr')->each(function (Crawler $row) use ($fieldMap, &$result, &$allLabels) {
            $cells = $row->filter('td');
            if ($cells->count() < 2) return;
            $label = trim($cells->eq(0)->text(''));
            $rawValue = trim($cells->last()->text(''));
            $allLabels[] = "{$label} => {$rawValue}";
            foreach ($fieldMap as $portalLabel => $dbCol) {
                if (strcasecmp($label, $portalLabel) === 0) {
                    $raw = $cells->last()->text('');
                    $val = trim(ltrim(trim($raw), ':'));
                    if (in_array(strtolower($val), ['data not found', '()', '-', ''])) {
                        $val = null;
                    }
                    $result[$dbCol] = $val;
                    break;
                }
            }
        });

        // DEBUG: Log all found labels and parsed results for first student
        if (file_get_contents(storage_path('logs/sample_profile_debug_done.flag')) === $nim) {
            Log::info("ProcessCampusScraping: DEBUG profile labels for {$nim}", ['labels' => $allLabels]);
            Log::info("ProcessCampusScraping: DEBUG parsed result for {$nim}", ['result' => $result]);
        }

        // Photo parsing and downloading
        $img = $crawler->filter('#studentPhoto img')->first();
        if ($img->count()) {
            $src = $img->attr('src');
            if ($src && !str_contains($src, 'no.photo')) {
                $result['profile_photo'] = $this->downloadAndSavePhoto($src, $nim);
            }
        }

        return $result;
    }

    private function downloadAndSavePhoto(string $src, string $nim): ?string
    {
        try {
            $baseUrl = 'https://igracias.telkomuniversity.ac.id';
            // Handle relative urls
            $url = str_starts_with($src, 'http') ? $src : $baseUrl . '/' . ltrim(str_replace('../', '', $src), '/');
            
            $response = Http::withoutVerifying()->withHeaders($this->httpHeaders())->timeout(15)->get($url);
            if ($response->successful()) {
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $path = 'foto-mahasiswa/' . $nim . '.' . $ext;
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Exception $e) {
            Log::warning("ProcessCampusScraping: Gagal download foto untuk {$nim}: " . $e->getMessage());
        }
        return null;
    }

    private function parseGradesData(string $html): array
    {
        $crawler = new Crawler($html);
        $result = ['total_credits' => null, 'gpa' => null];

        $crawler->filter('tr')->each(function (Crawler $row) use (&$result) {
            if (stripos($row->text(''), 'Total') === false) return;
            $row->filter('td, th')->each(function (Crawler $cell) use (&$result) {
                $text = trim($cell->text(''));
                if (preg_match('/^\d+([.,]\d+)?$/', $text)) {
                    $num = (float) str_replace(',', '.', $text);
                    if ($num > 4.0 && $result['total_credits'] === null) $result['total_credits'] = (int) $num;
                    elseif ($num <= 4.0 && $result['gpa'] === null) $result['gpa'] = round($num, 2);
                }
            });
        });

        if ($result['gpa'] === null || $result['total_credits'] === null) {
            $crawler->filter('td, th')->each(function (Crawler $cell) use (&$result) {
                $text = trim($cell->text(''));
                if ($result['gpa'] === null && preg_match('/IPK\s*[:=]\s*(\d+[.,]\d+)/i', $text, $m))
                    $result['gpa'] = round((float) str_replace(',', '.', $m[1]), 2);
                if ($result['total_credits'] === null && preg_match('/Total(?:\s+SKS)?\s*[:=]\s*(\d+)/i', $text, $m))
                    $result['total_credits'] = (int) $m[1];
            });
        }

        // DEBUG: Save sample grades HTML
        if (file_exists(storage_path('logs/sample_profile_debug_done.flag'))) {
            $debugNim = file_get_contents(storage_path('logs/sample_profile_debug_done.flag'));
            if (!file_exists(storage_path('logs/sample_grades_debug_done.flag'))) {
                file_put_contents(storage_path('logs/sample_grades_' . $debugNim . '.html'), $html);
                file_put_contents(storage_path('logs/sample_grades_debug_done.flag'), '1');
                Log::info("ProcessCampusScraping: DEBUG saved sample grades HTML", ['result' => $result]);
            }
        }

        return $result;
    }

    private function upsertStudent(string $nim, array $profileData, array $gradesData): void
    {
        $student = Student::where('student_number', $nim)->first();
        if ($student) {
            $student->update(array_merge(array_filter($profileData, fn($v) => $v !== null), array_filter($gradesData, fn($v) => $v !== null)));
        } else {
            Log::info("ProcessCampusScraping: Student number {$nim} belum ada di database, skip update profil & nilai.");
        }
    }

    //  PHASE 4: EPRT (languagecenter.ajax.languagetest.data.php)
    private function scrapeEprt(): void
    {
        try {
            $params = $this->buildEprtParams();
            $url = config('igracias.endpoints.eprt');
            $response = Http::withoutVerifying()->withHeaders($this->httpHeaders())->timeout(60)->get($url, $params);

            if ($this->isSessionExpired($response->body(), $response->status())) {
                Log::warning('ProcessCampusScraping: Phase 4 EPRT — Session expired, skipping.');
                return;
            }

            if ($this->isPermissionDenied($response->body())) {
                Log::info('ProcessCampusScraping: Phase 4 EPRT — Permission denied, skipping.');
                return;
            }

            $json = $response->json();
            $rows = $json['aaData'] ?? [];

            if (empty($rows)) {
                Log::info('ProcessCampusScraping: Phase 4 EPRT — Tidak ada data.');
                return;
            }

            $existingNims = Student::pluck('student_number')->flip();
            $saved = 0;

            $records = [];
            foreach ($rows as $row) {
                $parsed = $this->parseEprtRow($row);
                if ($parsed && $existingNims->has($parsed['student_number'])) {
                    $records[] = $parsed;
                }
            }

            if (!empty($records)) {
                $nimList = array_unique(array_column($records, 'student_number'));
                StudentEprt::whereIn('student_number', $nimList)->delete();

                // Insert batch
                foreach (array_chunk($records, 100) as $chunk) {
                    StudentEprt::insert(array_map(fn($r) => array_merge($r, [
                        'created_at' => now(), 'updated_at' => now(),
                    ]), $chunk));
                }
                $saved = count($records);
            }

            Log::info("ProcessCampusScraping: Phase 4 EPRT ✅ {$saved} records saved.");

        } catch (\Exception $e) {
            Log::warning("ProcessCampusScraping: Phase 4 EPRT ❌ Error: {$e->getMessage()}");
        }
    }

    // Parse 1 row EPRT DataTables.
    private function parseEprtRow(array $row): ?array
    {
        $nim = trim(strip_tags($row[1] ?? ''));
        if (!preg_match('/^\d{10,15}$/', $nim)) return null;

        return [
            'student_number'  => $nim,
            'student_name'    => trim(strip_tags($row[2] ?? '')) ?: null,
            'listening'       => is_numeric(trim(strip_tags($row[3] ?? ''))) ? (int) trim(strip_tags($row[3])) : null,
            'structure'       => is_numeric(trim(strip_tags($row[4] ?? ''))) ? (int) trim(strip_tags($row[4])) : null,
            'reading'         => is_numeric(trim(strip_tags($row[5] ?? ''))) ? (int) trim(strip_tags($row[5])) : null,
            'total_score'     => is_numeric(trim(strip_tags($row[6] ?? ''))) ? (int) trim(strip_tags($row[6])) : null,
            'details'         => trim(strip_tags($row[7] ?? '')) ?: null,
        ];
    }

    private function buildEprtParams(): array
    {
        $params = [
            'act' => 'PAviewScore', 'sEcho' => 2, 'iColumns' => 8, 'sColumns' => '',
            'iDisplayStart' => 0, 'iDisplayLength' => -1,
            'sSearch' => '', 'bRegex' => 'false',
            'iSortCol_0' => 0, 'sSortDir_0' => 'asc', 'iSortingCols' => 1,
            'testTypeFilter' => '',
        ];
        for ($i = 0; $i < 8; $i++) {
            $params["mDataProp_{$i}"] = $i;
            $params["sSearch_{$i}"] = '';
            $params["bRegex_{$i}"] = 'false';
            $params["bSearchable_{$i}"] = 'true';
            $params["bSortable_{$i}"] = 'true';
        }
        return $params;
    }

    //  PHASE 5: KRS (ajax.reg.php)
    //  NOTE: The KRS API does NOT support filter_school_year / filter_semester params.
    //  Passing them always returns 0 records. Instead, we fetch all data per prodi
    //  and extract year/semester from the response columns.
    private function scrapeKrs(): void
    {
        $existingNims = Student::pluck('student_number')->flip();
        $totalSaved = 0;
        $combIndex  = 0;
        
        $targetProdis = $this->prodiIds ? array_intersect(self::KRS_STUDY_PROGRAMS, $this->prodiIds) : self::KRS_STUDY_PROGRAMS;
        $totalCombs = count($targetProdis);

        foreach ($targetProdis as $prodi) {
            $combIndex++;

            try {
                $saved = $this->fetchAndSaveKrsPage($prodi, $existingNims);
                $totalSaved += $saved;
                Log::info("ProcessCampusScraping: KRS [{$combIndex}/{$totalCombs}] prodi={$prodi} → {$saved} records");
            } catch (\Exception $e) {
                Log::warning("ProcessCampusScraping: KRS [{$combIndex}/{$totalCombs}] ❌ {$e->getMessage()}");
            }

            if ($combIndex < $totalCombs) sleep(rand(2, 5)); // Humanize delay
        }

        Log::info("ProcessCampusScraping: Phase 5 KRS ✅ {$totalSaved} total records saved.");
    }

    private function fetchAndSaveKrsPage(int $prodi, $existingNims): int
    {
        $offset = 0;
        $saved  = 0;

        do {
            $params = $this->buildKrsParams($prodi, $offset);
            $url = config('igracias.endpoints.krs');
            $response = Http::withoutVerifying()
                ->withHeaders($this->httpHeaders())
                ->timeout(60)
                ->retry(3, 2000)
                ->get($url, $params);

            if ($this->isSessionExpired($response->body(), $response->status())) {
                Log::warning('ProcessCampusScraping: Phase 5 KRS — Session expired, skipping remaining.');
                return $saved;
            }

            if ($this->isPermissionDenied($response->body())) {
                Log::info("ProcessCampusScraping: Phase 5 KRS — Permission denied for prodi={$prodi}, skipping.");
                return $saved;
            }

            $json  = $response->json();
            $total = $json['iTotalDisplayRecords'] ?? 0;
            $rows  = $json['aaData'] ?? [];

            $page = ($offset / self::PAGE_SIZE) + 1;
            $totalPages = $total > 0 ? ceil($total / self::PAGE_SIZE) : 1;
            Log::info("ProcessCampusScraping: KRS prodi={$prodi} page {$page}/{$totalPages} ({$total} total records, saved so far: {$saved})");

            if (empty($rows)) break;

            foreach ($rows as $row) {
                $parsed = $this->parseKrsRow($row);
                if ($parsed && $existingNims->has($parsed['student_number'])) {
                    if ($parsed['faculty']) {
                        Student::where('student_number', $parsed['student_number'])->update(['faculty' => $parsed['faculty']]);
                    }

                    StudentCourse::updateOrCreate(
                        [
                            'student_number'  => $parsed['student_number'],
                            'course_code'     => $parsed['course_code'],
                            'academic_year'   => $parsed['academic_year'],
                            'semester'        => $parsed['semester'],
                        ],
                        $parsed,
                    );
                    $saved++;
                }
            }

            $offset += self::PAGE_SIZE;
            if ($offset < $total) sleep(rand(1, 3)); // Humanize delay
        } while ($offset < $total);

        return $saved;
    }

    /**
     * Parse 1 row KRS DataTables → mapping ke student_courses.
     *
     * Actual API response columns (verified via debug):
     *   [0]=NIM, [1]=Nama, [2]=Kelas Mahasiswa, [3]=Jenis Kelamin, [4]=Agama,
     *   [5]=Program Studi, [7]=KodeMK, [8]=NamaMK,
     *   [9]=KodeDosen, [10]=TahunAkademik, [11]=Semester, [12]=ProdiID,
     *   [13]=student_type, [14]=Kelas MK
     *   (Index 6 is ignored)
     */
    private function parseKrsRow(array $row): ?array
    {
        $nim = trim(strip_tags($row[0] ?? ''));
        if (!preg_match('/^\d{10,15}$/', $nim)) return null;

        return [
            'student_number' => $nim,
            'course_code'    => trim(strip_tags($row[7] ?? '')),
            'course_name'    => trim(strip_tags($row[8] ?? '')),
            'credits'        => null, // SKS not available in this endpoint
            'course_class'   => trim(strip_tags($row[14] ?? '')),
            'lecturer_code'  => trim(strip_tags($row[9] ?? '')) ?: null,
            'lecturer_name'  => null, // Not available in this endpoint
            'faculty'        => null, // Index 6 is ignored
            'study_program'  => trim(strip_tags($row[5] ?? '')) ?: null,
            'academic_year'  => trim(strip_tags($row[10] ?? '')),
            'semester'       => (int) (trim(strip_tags($row[11] ?? '1')) ?: 1),
        ];
    }

    /**
     * Build KRS request params.
     * NOTE: filter_school_year and filter_semester are intentionally left EMPTY.
     * The API returns 0 records when these are set (any format). Year/semester
     * data is instead extracted from the response columns [10] and [11].
     */
    private function buildKrsParams(int $prodi, int $offset): array
    {
        $params = [
            'act' => 'viewStudentCourse', 'sEcho' => 1, 'iColumns' => 15, 'sColumns' => '',
            'iDisplayStart' => $offset, 'iDisplayLength' => self::PAGE_SIZE,
            'sSearch' => '', 'bRegex' => 'false',
            'iSortCol_0' => 0, 'sSortDir_0' => 'asc', 'iSortingCols' => 1,
            'filter_school_year' => '', 'filter_semester' => '',
            'filter_subject_code' => '', 'filter_study_program' => $prodi, 'filter_faculty' => '',
        ];
        for ($i = 0; $i < 15; $i++) {
            $params["mDataProp_{$i}"] = $i;
            $params["sSearch_{$i}"] = '';
            $params["bRegex_{$i}"] = 'false';
            $params["bSearchable_{$i}"] = 'true';
            $params["bSortable_{$i}"] = 'true';
        }
        return $params;
    }

    //  SESSION & HELPERS
    private function isSessionExpired(string $body, int $statusCode): bool
    {
        // HTTP status checks
        if (in_array($statusCode, [401, 403])) return true;
        if ($statusCode >= 300 && $statusCode < 400) return true;

        // If the response is valid JSON with DataTables keys, the session is still alive.
        $json = json_decode($body, true);
        if (is_array($json) && (array_key_exists('aaData', $json) || array_key_exists('iTotalDisplayRecords', $json))) {
            return false;
        }

        // Cloudflare challenge = rate limiting, NOT session expiry
        $bodyLower = strtolower($body);
        if (str_contains($bodyLower, 'cf-browser-verification') || str_contains($bodyLower, 'cf_chl_opt')) {
            Log::warning('ProcessCampusScraping: Cloudflare challenge detected');
            return false;
        }

        // "you_dont_have_permission" = session alive, just no access
        if (str_contains($bodyLower, 'you_dont_have_permission')) return false;

        // Text-based checks for actual login page indicators
        $indicators = [
            'id="loginform"', "id='loginform'", 'name="textpassword"', "name='textpassword'",
            'sso username', 'session expired', 'sesi anda telah berakhir',
            'redirect_to_login', 'APPLICATION_ID__'
        ];
        foreach ($indicators as $ind) {
            if (str_contains($bodyLower, strtolower($ind))) return true;
        }
        return false;
    }

    /**
     * Check if iGracias returned a "permission denied" response.
     * This means the session IS valid but the account lacks access to this specific endpoint.
     */
    private function isPermissionDenied(string $body): bool
    {
        return str_contains(strtolower($body), 'you_dont_have_permission');
    }

    private function abortSessionExpired(string $context, string $detail, string $body = '', int $httpStatus = 0): void
    {
        Log::error('ProcessCampusScraping: 🔴 SESSION EXPIRED', [
            'context' => $context,
            'detail' => $detail,
            'http_status' => $httpStatus,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 1000),
        ]);
        $this->fail(new \RuntimeException('Session expired: Cookie tidak valid. Silakan login ulang.'));
    }

    /**
     * Sanitize cookie string: only keep essential session cookies.
     * Tracking cookies (GA, FB, Clarity, TikTok, Hotjar, Cloudflare, etc.)
     * make the Cookie header too long and cause Guzzle to reject it as invalid.
     */
    private function sanitizeCookie(string $raw): string
    {
        // Essential cookies for iGracias session
        $essentialPrefixes = ['PHPSESSID', 'BIGipServer', 'cf_clearance'];

        $parts = array_map('trim', explode(';', $raw));
        $kept  = [];

        foreach ($parts as $part) {
            if (empty($part)) continue;
            $name = trim(explode('=', $part, 2)[0]);
            foreach ($essentialPrefixes as $prefix) {
                if (stripos($name, $prefix) === 0) {
                    $kept[] = $part;
                    break;
                }
            }
        }

        $sanitized = implode('; ', $kept);

        if ($sanitized !== $raw) {
            $originalCount = count($parts);
            $keptCount     = count($kept);
            Log::info("ProcessCampusScraping: Cookie sanitized — kept {$keptCount}/{$originalCount} cookies.", [
                'kept_names' => array_map(fn($p) => explode('=', $p, 2)[0], $kept),
            ]);
        }

        return $sanitized;
    }

    private function httpHeaders(): array
    {
        $pageid = config('igracias.pageid');
        $referer = 'https://igracias.telkomuniversity.ac.id/';
        if (!empty($pageid)) {
            $referer .= '?pageid=' . $pageid;
        }

        return [
            'Cookie'           => $this->cookieString,
            'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Accept'           => 'text/html, application/json, application/xhtml+xml',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => $referer,
            'Accept-Language'  => 'en-US,en;q=0.9,id;q=0.8',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCampusScraping: 🔴 Job FAILED.', ['error' => $exception->getMessage()]);
    }
}
