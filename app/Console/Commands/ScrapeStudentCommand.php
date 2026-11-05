<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampusScraping;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeStudentCommand extends Command
{
    protected $signature = 'scrape:student
                            {--cookie= : Raw Cookie header string (e.g. PHPSESSID=abc123;...)}
                            {--cookie-file= : Path to a text file containing the cookie string}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Scrape student profile & grade data from iGracias campus portal';

    public function handle(): int
    {
        //  Resolve cookie string
        $cookie = $this->resolveCookie();
        if (!$cookie) return self::FAILURE;

        //  Check database has data
        $totalStudent = Student::count();
        $belumScrape    = Student::whereNull('gpa')->count();

        if ($totalStudent === 0) {
            $this->error('Tidak ada mahasiswa di database. Sinkronisasi data mahasiswa terlebih dahulu.');
            return self::FAILURE;
        }

        //  Print summary
        $this->info("┌─────────────────────────────────────────────────┐");
        $this->info("│  Campus Portal Scraper — Dadu 1.0               │");
        $this->info("├─────────────────────────────────────────────────┤");
        $this->info("│  Total Students   : {$totalStudent}");
        $this->info("│  Belum di-scrape  : {$belumScrape}");
        $this->info("│  Cookie           : " . substr($cookie, 0, 30) . '...');
        $this->info("│  Dispatch         : " . ($this->option('sync') ? 'SYNC' : 'QUEUED'));
        $this->info("│  Limit diatur di  : ProcessCampusScraping::MAX_STUDENTS");
        $this->info("└─────────────────────────────────────────────────┘");

        //  Dispatch job
        if ($this->option('sync')) {
            $this->warn('Running synchronously (--sync). This may take a while...');
            ProcessCampusScraping::dispatchSync($cookie);
        } else {
            ProcessCampusScraping::dispatch($cookie);
            $this->info('✅  Job dispatched to queue. Run `php artisan queue:work` to process.');
        }

        Log::info("ScrapeStudentCommand: Dispatched scraping job.");
        return self::SUCCESS;
    }

    /**
     * Resolve the cookie string from --cookie or --cookie-file.
     */
    private function resolveCookie(): ?string
    {
        $cookie     = $this->option('cookie');
        $cookieFile = $this->option('cookie-file');

        if ($cookieFile) {
            if (!file_exists($cookieFile)) {
                $this->error("Cookie file not found: {$cookieFile}");
                return null;
            }
            $cookie = trim(file_get_contents($cookieFile));
        }

        if (empty($cookie)) {
            $this->error('--cookie or --cookie-file is required.');
            $this->line('   Example: php artisan scrape:student --cookie="PHPSESSID=abc123"');
            $this->line('   Or:      php artisan scrape:student --cookie-file=cookie.txt');
            return null;
        }

        return $cookie;
    }
}
