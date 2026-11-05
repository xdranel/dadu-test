<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampusScraping;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Test scraping parsers tanpa koneksi ke kampus.
 * Menggunakan sample data palsu untuk memvalidasi logika parsing.
 *
 * Usage: php artisan scrape:test
 */
class TestScrapingParserCommand extends Command
{
    protected $signature = 'scrape:test
                            {--seed : Insert sample scraped data ke database agar tampil di UI}';
    protected $description = 'Test semua scraping parser dengan sample data (tanpa cookie/koneksi kampus)';

    public function handle(): int
    {
        $this->info("┌─────────────────────────────────────────────────┐");
        $this->info("│  Scraping Parser Test — Dadu 1.0                │");
        $this->info("│  Menguji logika parsing tanpa koneksi kampus    │");
        $this->info("└─────────────────────────────────────────────────┘");
        $this->newLine();

        $pass = 0;
        $fail = 0;

        //  Test 1: Parse Profile Data (Phase 2)
        $this->info('Test 1: parseProfileData (Phase 2 — viewdata)');
        try {
            $html = $this->sampleProfileHtml();
            $result = $this->callParser('parseProfileData', $html, '103072400079');

            $this->table(
                ['Field', 'Value'],
                collect($result)->map(fn($v, $k) => [$k, $v ?? '(null)'])->values()->toArray()
            );

            $checks = [
                $result['religion'] === 'ISLAM',
                $result['academic_advisor'] === 'Dr. Ahmad (1234)',
                $result['parent_phone_number'] === '081234567890',
                $result['high_school'] === 'SMAN 1 Bandung',
                $result['passed_tpb'] === 'Lulus',
                $result['passed_tkii'] === 'Lulus',
                $result['passed_tkiii'] === 'Belum',
                $result['passed_tkiv'] === null,
            ];

            if (count(array_filter($checks)) === count($checks)) {
                $this->info('PASSED — Semua field terparsing dengan benar.');
                $pass++;
            } else {
                $this->error('FAILED — Ada field yang tidak sesuai ekspektasi.');
                $fail++;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: {$e->getMessage()}");
            $fail++;
        }

        $this->newLine();

        //  Test 2: Parse Grades Data (Phase 3)
        $this->info('Test 2: parseGradesData (Phase 3 — viewstudyplan)');
        try {
            $html = $this->sampleGradesHtml();
            $result = $this->callParser('parseGradesData', $html);

            $this->table(['Field', 'Value'], collect($result)->map(fn($v, $k) => [$k, $v ?? '(null)'])->values()->toArray());

            if ($result['total_credits'] === 72 && $result['gpa'] === 3.45) {
                $this->info('PASSED — Total SKS dan IPK benar.');
                $pass++;
            } else {
                $this->error('FAILED — Nilai tidak sesuai (expected: sks=72, ipk=3.45).');
                $fail++;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: {$e->getMessage()}");
            $fail++;
        }

        $this->newLine();

        //  Test 3: Parse EPRT Row (Phase 4)
        $this->info('Test 3: parseEprtRow (Phase 4 — EPRT)');
        try {
            $row = ['1', '103072400079', 'John Doe', '45', '42', '48', '450', 'LULUS'];
            $result = $this->callParser('parseEprtRow', $row);

            $this->table(['Field', 'Value'], collect($result)->map(fn($v, $k) => [$k, $v ?? '(null)'])->values()->toArray());

            if ($result['student_number'] === '103072400079' && $result['total_score'] === 450 && $result['listening'] === 45) {
                $this->info('PASSED — EPRT row terparsing dengan benar.');
                $pass++;
            } else {
                $this->error('FAILED — Parsing EPRT tidak sesuai.');
                $fail++;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: {$e->getMessage()}");
            $fail++;
        }

        $this->newLine();

        //  Test 4: Parse KRS Row (Phase 5)
        $this->info('Test 4: parseKrsRow (Phase 5 — KRS)');
        try {
            // Actual API columns: [0]=NIM, [1]=Nama, [2]=Kelas, [3]=JK, [4]=Agama,
            //   [5]=Prodi, [7]=KodeMK, [8]=NamaMK, [9]=KodeDosen,
            //   [10]=TahunAkademik, [11]=Semester, [12]=ProdiID, [13]=student_type, [14]=KelasMK
            //   (Index 6 is ignored)
            $row = ['103072400079', 'John Doe', 'IF-01', 'PRIA', 'ISLAM',
                    'S1 Informatika', 'IGNORED_FIELD', 'IF101', 'Algoritma dan Pemrograman', 'D001',
                    '2526', '2', '121', '10', 'IF-01-MATA-KULIAH'];
            $result = $this->callParser('parseKrsRow', $row);

            $this->table(['Field', 'Value'], collect($result)->map(fn($v, $k) => [$k, $v ?? '(null)'])->values()->toArray());

            if ($result['student_number'] === '103072400079' && 
                $result['course_code'] === 'IF101' && 
                $result['semester'] === 2 &&
                $result['course_class'] === 'IF-01-MATA-KULIAH' &&
                $result['faculty'] === null) {
                $this->info('PASSED — KRS row terparsing dengan benar.');
                $pass++;
            } else {
                $this->error('FAILED — Parsing KRS tidak sesuai.');
                $fail++;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: {$e->getMessage()}");
            $fail++;
        }

        $this->newLine();

        //  Test 5: Session Expired Detection
        $this->info('Test 5: isSessionExpired detection');
        try {
            $expiredBodies = [
                'login_form'    => '<form id="loginform"><input name="textpassword" /></form>',
                'redirect'      => 'session expired, please login again',
                'app_id'        => 'var APPLICATION_ID__ = "12345"',
            ];

            $allPassed = true;
            foreach ($expiredBodies as $label => $body) {
                $expired = $this->callParser('isSessionExpired', $body, 200);
                $status = $expired ? '✅' : '❌';
                $this->line("   {$status} {$label}: " . ($expired ? 'detected' : 'NOT detected'));
                if (!$expired) $allPassed = false;
            }

            // Valid body should NOT be expired
            $validBody = str_repeat('x', 200) . '<table><tr><td>Data valid</td></tr></table>';
            $notExpired = !$this->callParser('isSessionExpired', $validBody, 200);
            $status = $notExpired ? '✅' : '❌';
            $this->line("   {$status} valid_body: " . ($notExpired ? 'correctly not expired' : 'false positive!'));
            if (!$notExpired) $allPassed = false;

            if ($allPassed) {
                $this->info('PASSED — Session detection bekerja benar.');
                $pass++;
            } else {
                $this->error('FAILED — Ada deteksi yang salah.');
                $fail++;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: {$e->getMessage()}");
            $fail++;
        }

        //  Summary
        $this->newLine();
        $this->info("┌─────────────────────────────────────────────────┐");
        $this->info("│  Results: {$pass} passed, {$fail} failed                     │");
        $this->info("└─────────────────────────────────────────────────┘");

        if ($fail > 0) {
            $this->error("⚠  Ada test yang gagal. Periksa parser sebelum menjalankan scraping.");
            return self::FAILURE;
        }

        //  Seed sample data ke database
        if ($this->option('seed')) {
            $this->newLine();
            $this->seedSampleData();
        } else {
            $this->info("✅ Semua parser siap! Tambahkan --seed untuk insert sample data ke DB:");
            $this->line("   php artisan scrape:test --seed");
        }

        return self::SUCCESS;
    }

    /**
     * Insert sample scraped data ke database agar bisa dilihat di UI.
     */
    private function seedSampleData(): void
    {
        $student = Student::first();

        if (!$student) {
            $this->error('Tidak ada mahasiswa di database. Sinkronisasi data dulu.');
            return;
        }

        $nim = $student->student_number;
        $this->info("Seeding sample data untuk NIM: {$nim} ({$student->name})");

        //  Update profil scraped fields
        $student->update([
            'parent_phone_number' => '081234567890',
            'high_school'         => 'SMAN 1 Bandung',
            'passed_tpb'          => 'Lulus',
            'passed_tkii'         => 'Lulus',
            'passed_tkiii'        => 'Belum',
            'passed_tkiv'         => null,
            'total_credits'       => 72,
            'gpa'                 => 3.45,
        ]);
        $this->line('Profil: IPK=3.45, SKS=72, Asal=SMAN 1 Bandung');

        //  Insert EPRT sample data
        StudentEprt::where('student_number', $nim)->delete();
        StudentEprt::insert([
            [
                'student_number' => $nim, 'student_name' => $student->name,
                'listening' => 45, 'structure' => 42, 'reading' => 48,
                'total_score' => 450, 'details' => 'LULUS',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'student_number' => $nim, 'student_name' => $student->name,
                'listening' => 38, 'structure' => 35, 'reading' => 40,
                'total_score' => 380, 'details' => 'TIDAK LULUS',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $this->line('EPRT: 2 records (skor 450 dan 380)');

        //  Insert KRS sample data
        $krsData = [
            ['kode' => 'IF101', 'nama' => 'Algoritma dan Pemrograman', 'sks' => 3, 'kelas' => 'IF-01', 'dosen' => 'Dr. Smith'],
            ['kode' => 'IF102', 'nama' => 'Basis Data', 'sks' => 3, 'kelas' => 'IF-01', 'dosen' => 'Dr. Jones'],
            ['kode' => 'IF103', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'kelas' => 'IF-02', 'dosen' => 'Dr. Brown'],
        ];

        foreach ($krsData as $krs) {
            StudentCourse::updateOrCreate(
                ['student_number' => $nim, 'course_code' => $krs['kode'], 'academic_year' => $student->academic_year, 'semester' => $student->active_semester],
                ['course_name' => $krs['nama'], 'credits' => $krs['sks'], 'course_class' => $krs['kelas'], 'lecturer_name' => $krs['dosen'], 'study_program' => $student->study_program],
            );
        }
        $this->line('KRS: 3 mata kuliah');

        $this->newLine();
        $this->info("Sample data berhasil di-seed! Buka di browser:");
        $this->line("→ Mahasiswa → {$student->name} → Lihat");
        $this->line("→ Cek tab: Profil, Rencana Studi & Nilai, EPRT");
    }

    /**
     * Call private method on ProcessCampusScraping using reflection.
     */
    private function callParser(string $method, ...$args): mixed
    {
        $job = new ProcessCampusScraping('dummy-cookie');
        $ref = new \ReflectionMethod($job, $method);
        $ref->setAccessible(true);
        return $ref->invoke($job, ...$args);
    }

    //  SAMPLE DATA

    private function sampleProfileHtml(): string
    {
        // Format 2-kolom: label di td pertama, value di td terakhir
        return <<<'HTML'
<table>
    <tr><td id="studentPhoto"><img src="https://igracias.telkomuniversity.ac.id/images/student/103072400079.jpg"></td><td></td></tr>
    <tr><td>NIM</td><td>103072400079</td></tr>
    <tr><td>Nama</td><td>John Doe</td></tr>
    <tr><td>Agama</td><td>: ISLAM</td></tr>
    <tr><td>Dosen Wali</td><td>: Dr. Ahmad (1234)</td></tr>
    <tr><td>No HP Ortu</td><td>: 081234567890</td></tr>
    <tr><td>Asal Sekolah</td><td>: SMAN 1 Bandung</td></tr>
    <tr><td>Alamat</td><td>: Jl. Raya No. 1</td></tr>
    <tr><td>Lulus TPB</td><td>: Lulus</td></tr>
    <tr><td>Lulus TKII</td><td>: Lulus</td></tr>
    <tr><td>Lulus TKIII</td><td>: Belum</td></tr>
    <tr><td>Status</td><td>Aktif</td></tr>
</table>
HTML;
    }

    private function sampleGradesHtml(): string
    {
        return <<<'HTML'
<table>
    <tr><th>No</th><th>Kode MK</th><th>Nama MK</th><th>SKS</th><th>Nilai</th></tr>
    <tr><td>1</td><td>IF101</td><td>Algoritma</td><td>3</td><td>A</td></tr>
    <tr><td>2</td><td>IF102</td><td>Basis Data</td><td>3</td><td>B+</td></tr>
    <tr><th colspan="3">Total</th><th>72</th><th>3.45</th></tr>
</table>
HTML;
    }
}
