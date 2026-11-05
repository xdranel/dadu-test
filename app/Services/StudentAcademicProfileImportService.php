<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentBppPayment;
use App\Models\StudentBasilaApproval;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentFinalProject;
use App\Models\StudentFinalProjectGrade;
use App\Models\StudentGraduationPayment;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk import data profil akademik gabungan (iGracias + BASILA).
 *
 * Menerima array `data` dari JSON yang berisi banyak jenis data sekaligus
 * per baris (per NIM), lalu pecah ke masing-masing tabel tujuan.
 *
 * Ketentuan matching:
 * - Cocokkan berdasarkan NIM + nama sekaligus.
 * - NIM + nama cocok → update.
 * - NIM tidak ditemukan → skip row + log.
 * - NIM ada tapi nama beda → skip row + log warning (kemungkinan typo nama
 *   atau mahasiswa lain, perlu dicek manual).
 *
 * Trade-off keputusan "NIM belum ada → skip":
 * Membuat record Student minimal tanpa data lengkap (tanpa prodi, kelas, dll)
 * bisa menyebabkan data tidak konsisten di modul lain. Lebih aman di-skip
 * dan dicatat di log supaya admin bisa import data student terlebih dahulu.
 */
class StudentAcademicProfileImportService
{
    /**
     * Import data profil akademik dari array JSON yang sudah di-parse.
     *
     * @param  array  $data  Array of rows dari JSON iGracias+BASILA
     * @return array  Summary ['created' => int, 'updated' => int, 'skipped' => int, 'mismatched' => int, 'errors' => array]
     */
    public function import(array $data, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        // Nested fixtures/imports remain readable; current flat exports use the normalizer.
        $legacyContainers = ['tugas_akhir', 'tak', 'basila', 'sidang_tingkat', 'course_issues',
            'bpp', 'nilai_ta', 'eprt', 'skpi', 'pembayaran_wisuda'];
        $officialFields = ['status_skpi', 'persetujuan_maju_sidang', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa'];
        $legacyOnly = $selectedMenus === null && $financePeriod === null;
        foreach ($data as $row) {
            if (!is_array($row) || array_intersect(array_keys($row), $legacyContainers) === []
                || array_intersect(array_keys($row), $officialFields) !== []) {
                $legacyOnly = false;
                break;
            }
        }
        if (!$legacyOnly) {
            return app(AcademicProfileJsonImportService::class)->import($data, $selectedMenus, $financePeriod);
        }
        $summary = [
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'mismatched' => 0,
            'errors'     => [],
        ];

        foreach ($data as $index => $row) {
            if (!is_array($row)) {
                $summary['skipped']++;
                continue;
            }

            $nim  = trim($row['nim'] ?? '');
            $nama = trim($row['nama'] ?? '');

            // Field `no` dan `error` diabaikan sepenuhnya saat mapping
            // (tidak perlu unset, cukup tidak dipakai)

            if (empty($nim)) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM kosong, dilewati.";
                continue;
            }

            // ─── Validasi kecocokan NIM + Nama ──────────────────────────
            $student = Student::where('student_number', $nim)->first();

            if (!$student) {
                // NIM tidak ditemukan di database → skip.
                // Alasan: lebih aman daripada membuat Student minimal tanpa
                // data lengkap (prodi, kelas, dll). Admin harus import data
                // student terlebih dahulu via import profil mahasiswa.
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM {$nim} ({$nama}) tidak ditemukan di database, dilewati.";
                Log::warning('AcademicProfileImport: NIM tidak ditemukan', [
                    'nim'  => $nim,
                    'nama' => $nama,
                ]);
                continue;
            }

            // Cek kecocokan nama (case-insensitive, trim whitespace)
            if (strtolower(trim($student->name)) !== strtolower($nama)) {
                // NIM ada tapi nama beda — kemungkinan typo nama di JSON
                // atau data untuk mahasiswa yang berbeda.
                // Keputusan: skip + log warning, supaya bisa dicek manual.
                // Jangan diam-diam menimpa data mahasiswa lain.
                $summary['mismatched']++;
                $summary['errors'][] = "Baris #{$index}: NIM {$nim} ada di DB dengan nama \"{$student->name}\", tapi JSON nama \"{$nama}\" (beda). Dilewati — cek manual.";
                Log::warning('AcademicProfileImport: NIM cocok tapi nama beda', [
                    'nim'       => $nim,
                    'nama_json' => $nama,
                    'nama_db'   => $student->name,
                ]);
                continue;
            }

            // ─── NIM + Nama cocok → lanjut upsert ──────────────────────
            try {
                DB::transaction(function () use ($row, $nim, $student, &$summary) {
                    $isNew = $this->upsertAllTables($row, $nim, $student);
                    $isNew ? $summary['created']++ : $summary['updated']++;
                });
                app(GraduationDataSyncService::class)->syncExistingRegistrations($nim);
            } catch (\Exception $e) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris NIM {$nim}: Error — {$e->getMessage()}";
                Log::error('AcademicProfileImport: Error saat upsert', [
                    'nim'   => $nim,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Upsert semua tabel tujuan untuk satu baris data.
     *
     * @return bool  True jika ini first-time insert (baru), false jika update
     */
    private function upsertAllTables(array $row, string $nim, Student $student): bool
    {
        $thesis = is_array($row['tugas_akhir'] ?? null) ? $row['tugas_akhir'] : [];
        $tak = is_array($row['tak'] ?? null) ? $row['tak'] : [];
        $basila = is_array($row['basila'] ?? null) ? $row['basila'] : [];
        $sidang = is_array($row['sidang_tingkat'] ?? null) ? $row['sidang_tingkat'] : [];
        if (array_is_list($sidang) && $sidang !== []) {
            $sidang = array_combine(['tingkat_1', 'tingkat_2', 'tingkat_3', 'tingkat_4'], array_pad(array_slice($sidang, 0, 4), 4, null));
        }
        $courseIssues = is_array($row['course_issues'] ?? null) ? $row['course_issues'] : [];
        $bpp = is_array($row['bpp'] ?? null) ? $row['bpp'] : [];
        $thesisGrade = is_array($row['nilai_ta'] ?? null) ? $row['nilai_ta'] : [];
        $eprt = is_array($row['eprt'] ?? null) ? $row['eprt'] : [];

        // Track apakah ini pertama kali (semua tabel baru → created)
        $wasNew = !StudentSidangTingkat::where('student_number', $nim)->exists();

        // ─── Update tabel `students` (field duplikat dari JSON) ─────────
        $studentUpdates = [];

        if (isset($row['ipk']) && $row['ipk'] !== null) {
            $studentUpdates['gpa'] = $this->toNumeric($row['ipk']);
        }
        if (isset($row['sks_lulus']) && $row['sks_lulus'] !== null) {
            $studentUpdates['total_credits'] = $this->toNumeric($row['sks_lulus']);
        }
        if (isset($row['dosen_wali']) && $row['dosen_wali'] !== null) {
            $studentUpdates['academic_advisor'] = $row['dosen_wali'];
        }
        if (isset($row['semester_aktif']) || isset($row['active_semester'])) {
            $studentUpdates['active_semester'] = $this->toNumeric($row['semester_aktif'] ?? $row['active_semester'] ?? null);
        }

        if (!empty($studentUpdates)) {
            $student->update($studentUpdates);
        }

        // ─── Data Tugas Akhir ───────────────────────────────────────────
        StudentFinalProject::updateOrCreate(
            ['student_number' => $nim],
            array_filter([
                'thesis_title_id' => $thesis['judul_ta_id'] ?? $thesis['judul_ta'] ?? $thesis['judul_indonesia'] ?? $row['judul_ta_id'] ?? $row['judul_ta'] ?? null,
                'thesis_title_en' => $thesis['judul_ta_en'] ?? $thesis['judul_inggris'] ?? $row['judul_ta_en'] ?? null,
                'advisor_1'     => $this->personName($thesis['pembimbing_1'] ?? $row['pembimbing_1'] ?? null),
                'advisor_2'     => $this->personName($thesis['pembimbing_2'] ?? $row['pembimbing_2'] ?? null),
                'examiner_1'    => $this->personName($thesis['penguji_1'] ?? $row['penguji_1'] ?? null),
                'examiner_2'    => $this->personName($thesis['penguji_2'] ?? $row['penguji_2'] ?? null),
                'examiner_3'    => $this->personName($thesis['penguji_3'] ?? $row['penguji_3'] ?? null),
                'exam_date'     => $this->toDate($thesis['tanggal_sidang'] ?? $row['tanggal_sidang'] ?? null),
                'exam_time'     => $thesis['waktu'] ?? $row['waktu'] ?? null,
                'exam_location' => $thesis['tempat'] ?? $row['tempat'] ?? null,
            ], fn ($value) => $value !== null)
        );

        // ─── TAK (Total poin - Multi Row) ──────────────────────────────
        $takRecords = $tak !== [] ? (array_is_list($tak) ? $tak : [$tak]) : (isset($row['total_tak']) ? [$row] : []);
        foreach ($takRecords as $takRecord) {
            if (!is_array($takRecord)) {
                continue;
            }
            $takScore = $takRecord['total_tak'] ?? $takRecord['total_points'] ?? null;
            if ($takScore === null) {
                continue;
            }

            StudentTak::updateOrCreate(
                [
                    'student_number' => $nim,
                    'test_date'      => $this->toDate($takRecord['tanggal_tes_tak'] ?? $takRecord['test_date'] ?? $takRecord['tanggal_tes'] ?? null),
                ],
                [
                    'total_points' => $this->toNumeric((string) $takScore),
                    'expiry_date'  => $this->toDate($takRecord['masa_berlaku_tak'] ?? $takRecord['expiry_date'] ?? null),
                ]
            );
        }

        // ─── EPRT (nilai_bahasa → total_score) ──────────────────────────
        $eprtRecords = $eprt !== [] ? (array_is_list($eprt) ? $eprt : [$eprt]) : [];
        if ($eprtRecords === [] && (isset($row['nilai_bahasa']) || isset($row['eprt_score']))) {
            $eprtRecords = [['score' => $row['nilai_bahasa'] ?? $row['eprt_score']]];
        }
        foreach ($eprtRecords as $attempt => $eprtRecord) {
            if (!is_array($eprtRecord)) {
                $eprtRecord = ['score' => $eprtRecord];
            }
            $eprtScore = $eprtRecord['score'] ?? $eprtRecord['nilai'] ?? $eprtRecord['total_score'] ?? null;
            if ($eprtScore === null) {
                continue;
            }
            StudentEprt::updateOrCreate(
                [
                    'student_number' => $nim,
                    'attempt_number' => $eprtRecord['attempt_number'] ?? ($attempt + 1),
                ],
                [
                    'total_score' => $this->toNumeric((string) $eprtScore),
                    'test_date' => $this->toDate($eprtRecord['tanggal_tes'] ?? $eprtRecord['test_date'] ?? null),
                ]
            );
        }

        // ─── Sidang Tingkat 1-4 ─────────────────────────────────────────
        StudentSidangTingkat::updateOrCreate(
            ['student_number' => $nim],
            [
                'tingkat_1' => $this->toBool((string) ($sidang['tingkat_1'] ?? $sidang['1'] ?? $row['sidang_tingkat_1'] ?? null)),
                'tingkat_2' => $this->toBool((string) ($sidang['tingkat_2'] ?? $sidang['2'] ?? $row['sidang_tingkat_2'] ?? null)),
                'tingkat_3' => $this->toBool((string) ($sidang['tingkat_3'] ?? $sidang['3'] ?? $row['sidang_tingkat_3'] ?? null)),
                'tingkat_4' => $this->toBool((string) ($sidang['tingkat_4'] ?? $sidang['4'] ?? $row['sidang_tingkat_4'] ?? null)),
            ]
        );

        // ─── Approval BASILA ────────────────────────────────────────────
        $rawBasilaStatus = strtolower((string) ($basila['status'] ?? $basila['approval_status'] ?? $row['basila_status'] ?? ''));
        $basilaStatus = match(true) {
            in_array($rawBasilaStatus, ['approved', 'disetujui'], true) => 'approved',
            in_array($rawBasilaStatus, ['rejected', 'ditolak'], true) => 'rejected',
            $this->toBool((string) ($basila['approval_foto_dosen_wali'] ?? $row['approval_foto_dosen_wali_basila'] ?? null)) => 'approved',
            default => 'pending',
        };

        StudentBasilaApproval::updateOrCreate(
            ['student_number' => $nim],
            [
                'photo_approval' => $basilaStatus === 'approved',
                'status'         => $basilaStatus,
            ]
        );

        // ─── Nilai Tugas Akhir (indeks) ─────────────────────────────────
        $grade = $row['indeks_nilai_tugas_akhir'] ?? $thesisGrade['indeks'] ?? $thesisGrade['grade'] ?? null;
        if ($grade !== null) {
            StudentFinalProjectGrade::updateOrCreate(
                ['student_number' => $nim],
                ['grade' => $grade]
            );
        }

        // ─── MK Mengulang & MK Belum Lulus + SKS ───────────────────────
        StudentCourseIssue::updateOrCreate(
            ['student_number' => $nim],
            [
                'retaking_courses'  => $this->toJsonArray($courseIssues['mk_mengulang'] ?? $row['mk_mengulang'] ?? null),
                'failed_courses'    => $this->toJsonArray($courseIssues['mk_belum_lulus'] ?? $row['mk_belum_lulus'] ?? null),
                'credits_passed'    => $this->toNumeric((string) ($courseIssues['sks_lulus'] ?? $row['sks_lulus'] ?? '')),
                'credits_remaining' => $this->toNumeric((string) ($courseIssues['sks_belum_lulus'] ?? $row['sks_belum_lulus'] ?? '')),
            ]
        );

        // ─── Status Pembayaran BPP (dari field status_keuangan) ─────────
        StudentBppPayment::updateOrCreate(
            ['student_number' => $nim],
            ['status' => $bpp['status'] ?? $row['status_keuangan'] ?? null]
        );

        // ─── Status Pembayaran Wisuda ───────────────────────────────────
        $graduationPayment = is_array($row['pembayaran_wisuda'] ?? null) ? $row['pembayaran_wisuda'] : [];
        if (isset($row['status_keuangan_wisuda']) || isset($row['biaya_wisuda']) || isset($graduationPayment['status'])) {
            StudentGraduationPayment::updateOrCreate(
                ['student_number' => $nim, 'payment_type' => 'wisuda'],
                [
                    'status' => $row['status_keuangan_wisuda'] ?? $graduationPayment['status'] ?? null,
                    'amount' => $this->toNumeric((string) ($row['biaya_wisuda'] ?? $graduationPayment['amount'] ?? '')),
                ]
            );
        }

        $skpi = is_array($row['skpi'] ?? null) ? $row['skpi'] : null;
        if ($skpi !== null) {
            StudentSkpi::updateOrCreate(['student_number' => $nim], ['raw_data' => $skpi]);
        }

        // Invalidasi Cache per NIM setelah update
        \Illuminate\Support\Facades\Cache::forget("campus:{$nim}");

        return $wasNew;
    }

    // ─── Konversi Tipe Data ─────────────────────────────────────────────

    /**
     * Konversi "Y"/"N" ke boolean. Null tetap false.
     */
    private function toBool(mixed $value): bool
    {
        if ($value === null || $value === false || $value === 0 || $value === '0') {
            return false;
        }
        if ($value === true || $value === 1) {
            return true;
        }

        return in_array(strtolower(trim((string) $value)), ['y', 'yes', 'true', '1', 'passed', 'lulus', 'approved', 'disetujui'], true);
    }

    /**
     * Konversi string angka ("3.77", "146") ke numeric. Null tetap null.
     */
    private function toNumeric(mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Konversi string tanggal ke format Y-m-d. Null tetap null.
     */
    private function toDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Konversi value ke JSON array.
     * - null → null
     * - string kosong → null
     * - string yang sudah JSON array → decode dan kembalikan array
     * - string comma-separated → pecah jadi array
     */
    private function toJsonArray(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        // Coba decode sebagai JSON dulu
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fallback: comma-separated string
        return array_filter(array_map('trim', explode(',', $value)));
    }

    private function personName(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['nama'] ?? $value['name'] ?? $value['nama_dosen'] ?? null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
