<?php

namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCourseScraping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 3600; 

    private const REG_URL = 'https://igracias.telkomuniversity.ac.id/libraries/ajax/ajax.reg.php';
    private const REQUEST_DELAY = 2;    
    private const PAGE_SIZE     = 200; 
    private const FACULTY_ID    = 10;
    private const ACTIVE_YEARS = [2018, 2020, 2021, 2022, 2024];
    private const SCHOOL_YEARS = [1819, 1920, 2021, 2122, 2223, 2324, 2425, 2526];
    private const SEMESTERS    = [1, 2];

    public function __construct(
        private string $cookieString,
    ) {}

    public function handle(): void
    {
        Log::info('ProcessCourseScraping: ▶ Job started.');

        $totalSaved = 0;
        $combIndex  = 0;
        $totalCombs = count(self::ACTIVE_YEARS) * count(self::SCHOOL_YEARS) * count(self::SEMESTERS);

        foreach (self::ACTIVE_YEARS as $activeYear) {
            foreach (self::SCHOOL_YEARS as $schoolYear) {
                foreach (self::SEMESTERS as $semester) {
                    $combIndex++;

                    try {
                        $saved = $this->fetchAndSavePage($activeYear, $schoolYear, $semester);
                        $totalSaved += $saved;

                        if ($saved > 0) {
                            Log::info("ProcessCourseScraping: [{$combIndex}/{$totalCombs}] activeYear={$activeYear} schoolYear={$schoolYear} sem={$semester} → {$saved} records");
                        } else {
                            Log::debug("ProcessCourseScraping: [{$combIndex}/{$totalCombs}] activeYear={$activeYear} schoolYear={$schoolYear} sem={$semester} → 0 records");
                        }
                    } catch (\Exception $e) {
                        Log::warning("ProcessCourseScraping: [{$combIndex}/{$totalCombs}] ❌ {$e->getMessage()}");
                    }

                    if ($combIndex < $totalCombs) {
                        sleep(self::REQUEST_DELAY);
                    }
                }
            }
        }

        Log::info("ProcessCourseScraping: Job completed. {$totalSaved} total records saved/updated.");
    }

    private function fetchAndSavePage(int $activeYear, int $schoolYear, int $semester): int
    {
        $offset = 0;
        $saved  = 0;

        do {
            $params = $this->buildParams($activeYear, $schoolYear, $semester, $offset);
            $response = Http::withoutVerifying()
                ->withHeaders($this->httpHeaders())
                ->timeout(30)
                ->get(self::REG_URL, $params);

            if ($this->isSessionExpired($response->body(), $response->status())) {
                $this->abortSessionExpired(
                    'fetchMatakuliah',
                    "activeYear={$activeYear}, schoolYear={$schoolYear}, sem={$semester}"
                );
                return $saved;
            }

            $json  = $response->json();
            $total = $json['iTotalDisplayRecords'] ?? 0;
            $rows  = $json['aaData'] ?? [];
            if (empty($rows)) break;

            $records = [];
            foreach ($rows as $row) {
                $parsed = $this->parseRow($row, $semester);
                if ($parsed) {
                    $records[] = $parsed;
                }
            }

            if (!empty($records)) {
                foreach (array_chunk($records, 500) as $chunk) {
                    Course::upsert(
                        $chunk,
                        ['course_code'],
                        ['name', 'study_program', 'class', 'semester', 'credits', 'type', 'status', 'updated_at'] // Columns to update
                    );
                }
                $saved += count($records);
            }

            $offset += self::PAGE_SIZE;
            if ($offset < $total) {
                sleep(1);
            }
        } while ($offset < $total);

        return $saved;
    }

    private function parseRow(array $row, int $semester): ?array
    {
        $courseCode = trim(strip_tags($row[1] ?? ''));
        if (empty($courseCode)) return null;

        $name     = trim(strip_tags($row[2] ?? ''));
        $credits  = trim(strip_tags($row[4] ?? ''));
        $class    = trim(strip_tags($row[5] ?? ''));
        $studyProgram = trim(strip_tags($row[10] ?? ''));
        $type     = trim(strip_tags($row[17] ?? ''));
        $status   = trim(strip_tags($row[19] ?? ''));

        return [
            'course_code'   => strtoupper($courseCode),
            'name'          => $name ?: '',
            'study_program' => $studyProgram ?: '',
            'class'         => $class ?: 'A',
            'semester'      => $semester,
            'credits'       => is_numeric($credits) ? (int) $credits : 3,
            'type'          => $this->normalizeJenis($type),
            'status'        => $this->normalizeStatus($status),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

    private function normalizeJenis(string $jenis): string
    {
        $lower = strtolower(trim($jenis));
        if (str_contains($lower, 'wajib')) return 'Wajib';
        if (str_contains($lower, 'pilihan') || str_contains($lower, 'elective')) return 'Pilihan';
        return $jenis ?: 'Wajib';
    }

    private function normalizeStatus(string $status): string
    {
        $lower = strtolower(trim($status));
        if (str_contains($lower, 'aktif') && !str_contains($lower, 'tidak')) return 'Aktif';
        if (str_contains($lower, 'tidak') || str_contains($lower, 'inactive')) return 'Tidak Aktif';
        return $status ?: 'Aktif';
    }

    private function buildParams(int $activeYear, int $schoolYear, int $semester, int $offset): array
    {
        $iColumns = 21;

        $params = [
            'act'            => 'view',
            'sEcho'          => 1,
            'iColumns'       => $iColumns,
            'sColumns'       => '',
            'iDisplayStart'  => $offset,
            'iDisplayLength' => self::PAGE_SIZE,
            'sSearch'        => '',
            'bRegex'         => 'false',
            'iSortCol_0'     => 0,
            'sSortDir_0'     => 'asc',
            'iSortingCols'   => 1,
            'activeYear'     => $activeYear,
            'facultyId'      => self::FACULTY_ID,
            'studyProgramId' => '',
            'schoolYear'     => $schoolYear,
            'semester'       => $semester,
            'subjectType'    => '',
            'fix'            => 'all',
            'classType'      => '',
            'stat'           => '',
            'coursetype'     => '',
        ];

        $searchable = [
            0 => false, 1 => true, 2 => true, 3 => true, 4 => false,
            5 => false, 6 => false, 7 => false, 8 => false, 9 => false,
            10 => true, 11 => true, 12 => true, 13 => false, 14 => false,
            15 => false, 16 => false, 17 => true, 18 => false, 19 => true, 20 => true,
        ];
        $sortable = [
            0 => false, 1 => true, 2 => true, 3 => true, 4 => true,
            5 => true, 6 => true, 7 => true, 8 => true, 9 => true,
            10 => true, 11 => true, 12 => true, 13 => true, 14 => true,
            15 => true, 16 => true, 17 => true, 18 => true, 19 => false, 20 => true,
        ];

        for ($i = 0; $i < $iColumns; $i++) {
            $params["mDataProp_{$i}"]  = $i;
            $params["sSearch_{$i}"]    = '';
            $params["bRegex_{$i}"]     = 'false';
            $params["bSearchable_{$i}"] = ($searchable[$i] ?? true) ? 'true' : 'false';
            $params["bSortable_{$i}"]   = ($sortable[$i] ?? true) ? 'true' : 'false';
        }

        return $params;
    }

    private function isSessionExpired(string $body, int $statusCode): bool
    {
        if (in_array($statusCode, [401, 403])) return true;
        if ($statusCode >= 300 && $statusCode < 400) return true;
        if (strlen(trim($body)) < 100) return true;

        $indicators = [
            'id="loginform"', "id='loginform'", 'name="textpassword"', "name='textpassword'", 
            'sso username', 'session expired', 'sesi anda telah berakhir', 'loginform',
            'redirect_to_login', 'you_dont_have_permission', 'APPLICATION_ID__'
        ];
        $bodyLower = strtolower($body);
        foreach ($indicators as $ind) {
            if (str_contains($bodyLower, strtolower($ind))) return true;
        }
        return false;
    }

    private function abortSessionExpired(string $context, string $detail): void
    {
        Log::error('ProcessCourseScraping: SESSION EXPIRED', compact('context', 'detail'));
        $this->fail(new \RuntimeException('Session expired: Cookie tidak valid. Silakan login ulang.'));
    }

    private function httpHeaders(): array
    {
        return [
            'Cookie'           => $this->cookieString,
            'User-Agent'       => 'Dadu/1.0 (Internal Academic Tool)',
            'Accept'           => 'text/html, application/json, application/xhtml+xml',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCourseScraping: Job FAILED.', ['error' => $exception->getMessage()]);
    }
}
