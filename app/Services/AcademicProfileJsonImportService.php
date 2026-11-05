<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentBasilaApproval;
use App\Models\StudentBppPayment;
use App\Models\StudentCourseIssue;
use App\Models\StudentFinalProject;
use App\Models\StudentFinalProjectGrade;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Support\AcademicProfileJsonNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/** Imports the current flat JSON format. Does not change final TA snapshots. */
class AcademicProfileJsonImportService
{
    public function import(array $rows, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        $normalizer = new AcademicProfileJsonNormalizer;
        // Validate menu context even for an empty batch, before any write.
        $normalizer->normalize([], $selectedMenus, $financePeriod);
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'mismatched' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_string($row['nim'] ?? null) || !is_string($row['nama'] ?? null)
                || trim($row['nim']) === '' || trim($row['nama']) === '') {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM atau nama tidak valid.";
                continue;
            }
            $nim = trim($row['nim']);
            try {
                $outcome = DB::transaction(function () use ($row, $nim, $selectedMenus, $financePeriod, $normalizer) {
                    // The parent row serializes imports for the same student.
                    $student = Student::where('student_number', $nim)->lockForUpdate()->first();
                    if (!$student) return ['kind' => 'skipped', 'warnings' => ['Mahasiswa belum terdaftar.']];
                    if (mb_strtolower(trim($student->name)) !== mb_strtolower(trim($row['nama']))) {
                        return ['kind' => 'mismatched', 'warnings' => ['NIM dan nama tidak cocok.']];
                    }

                    $result = $normalizer->normalize($row, $selectedMenus, $financePeriod);
                    $updates = $result['updates'];
                    $warnings = array_values($result['skipped']);
                    $failed = $normalizer->failures($row['error'] ?? null);
                    foreach (array_intersect($failed, ['akademik', 'mata_kuliah', 'jadwal_sidang', 'basila_foto']) as $menu) {
                        $warnings[] = 'Pengambilan data menu '.$menu.' gagal.';
                    }
                    $allowed = fn (array $menus) => !in_array('*', $failed, true)
                        && array_intersect($failed, $menus) === []
                        && ($selectedMenus === null || array_intersect($selectedMenus, $menus) !== []);
                    $changed = false;

                    if ($allowed(['akademik', 'mata_kuliah'])) {
                        try {
                            $academic = DB::transaction(fn () => $this->academic($row, $student, $nim));
                            if ($academic !== []) {
                                $updates['academic'] = $academic;
                                $changed = true;
                            }
                        } catch (InvalidArgumentException $exception) {
                            // academic() validates its patches before writing any table.
                            $warnings[] = $exception->getMessage();
                        }
                    }
                    if ($allowed(['jadwal_sidang'])) {
                        try {
                            $changed = DB::transaction(fn () => $this->project($row, $nim)) || $changed;
                        } catch (InvalidArgumentException $exception) {
                            $warnings[] = $exception->getMessage();
                        }
                    }
                    if ($allowed(['basila_foto']) && array_key_exists('approval_foto_dosen_wali_basila', $row)) {
                        try {
                            $photo = $this->boolean($row['approval_foto_dosen_wali_basila']);
                            DB::transaction(fn () => $this->upsert(StudentBasilaApproval::class, $nim,
                                ['photo_approval' => $photo === true, 'status' => $photo === true ? 'approved' : 'pending']));
                            $changed = true;
                        } catch (InvalidArgumentException $exception) {
                            $warnings[] = $exception->getMessage();
                        }
                    }

                    if (isset($updates['skpi'])) {
                        $raw = array_intersect_key($row, array_flip(['status_skpi', 'skpi_status_1', 'skpi_status_2', 'skpi_status_3']));
                        // Keep the old table readable until the facts reader is updated.
                        $raw['approval_status'] = $updates['skpi']['approved'] ? 'approved' : 'rejected';
                        $this->upsert(StudentSkpi::class, $nim, ['raw_data' => $raw]);
                    }
                    if (isset($updates['tak'])) {
                        StudentTak::updateOrCreate(['student_number' => $nim, 'test_date' => null],
                            ['total_points' => $updates['tak']['score'], 'expiry_date' => null]);
                    }
                    if (isset($updates['bpp'])) {
                        $this->upsert(StudentBppPayment::class, $nim, ['status' => $updates['bpp']['status']]);
                    }

                    if ($updates === [] && !$changed) {
                        return ['kind' => 'skipped', 'warnings' => $warnings ?: ['Tidak ada data yang dapat diperbarui.']];
                    }

                    $profile = StudentAcademicProfile::where('student_number', $nim)->first();
                    $kind = $profile ? 'updated' : 'created';
                    $profile ??= new StudentAcademicProfile(['student_number' => $nim]);
                    $groups = $profile->groups ?? [];
                    foreach ($updates as $group => $values) {
                        // Academic fields may be individually omitted; language is atomic.
                        $groups[$group] = $group === 'academic'
                            ? array_replace($groups[$group] ?? [], $values) : $values;
                    }
                    $periods = $profile->bpp_periods ?? [];
                    if (isset($updates['bpp']['period'])) {
                        $periods[$updates['bpp']['period']] = $updates['bpp'];
                    }
                    $profile->fill(['groups' => $groups, 'bpp_periods' => $periods])->save();
                    return ['kind' => $kind, 'warnings' => $warnings];
                }, 3);
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: penyimpanan gagal; perubahan baris dibatalkan.";
                Log::warning('Academic JSON row rolled back', ['row' => $index, 'exception' => $exception::class]);
                continue;
            }

            $summary[$outcome['kind']]++;
            foreach ($outcome['warnings'] as $warning) $summary['errors'][] = "Baris #{$index}: {$warning}";
            if (!in_array($outcome['kind'], ['created', 'updated'], true)) continue;
            // These side effects run only after the row's transaction committed.
            try {
                Cache::forget("campus:{$nim}");
                app(GraduationDataSyncService::class)->syncExistingRegistrations($nim);
            } catch (Throwable $exception) {
                $summary['errors'][] = "Baris #{$index}: data tersimpan, penyegaran tampilan terkait belum berhasil.";
                Log::warning('Academic JSON refresh failed', ['row' => $index, 'exception' => $exception::class]);
            }
        }
        return $summary;
    }

    private function academic(array $row, Student $student, string $nim): array
    {
        $studentPatch = $issues = $levels = $facts = [];
        foreach (['ipk' => ['gpa', 'gpa'], 'sks_lulus' => ['total_credits', 'passed_credits']] as $key => [$column, $fact]) {
            if (!array_key_exists($key, $row)) continue;
            $value = $this->number($row[$key]);
            if ($key === 'sks_lulus' && $value !== null && floor($value) !== $value) {
                throw new InvalidArgumentException('SKS harus berupa bilangan bulat.');
            }
            $studentPatch[$column] = $value;
            $facts[$fact] = $value;
            if ($key === 'sks_lulus') $issues['credits_passed'] = $value;
        }
        if (array_key_exists('dosen_wali', $row)) $studentPatch['academic_advisor'] = $this->text($row['dosen_wali']);
        if ((is_string($row['semester'] ?? null) || is_int($row['semester'] ?? null))
            && preg_match('/^\d+$/D', (string) $row['semester']) === 1) {
            $semester = (int) $row['semester'];
            if ($semester > 0 && $semester <= 127) $studentPatch['active_semester'] = $semester;
        }
        foreach (range(1, 4) as $level) {
            $key = 'sidang_tingkat_'.$level;
            if (!array_key_exists($key, $row)) continue;
            $value = $this->boolean($row[$key]);
            $levels['tingkat_'.$level] = $value === true;
            if ($level === 2) $facts['level_2_passed'] = $value;
        }
        if (array_key_exists('sks_belum_lulus', $row)) {
            $value = $this->number($row['sks_belum_lulus']);
            if ($value !== null && floor($value) !== $value) throw new InvalidArgumentException('SKS harus berupa bilangan bulat.');
            $issues['credits_remaining'] = $value;
        }
        foreach (['mk_mengulang' => 'retaking_courses', 'mk_belum_lulus' => 'failed_courses'] as $key => $column) {
            if (!array_key_exists($key, $row)) continue;
            $issues[$column] = $this->courseList($row[$key]);
        }
        $grade = array_key_exists('indeks_nilai_tugas_akhir', $row) ? $this->text($row['indeks_nilai_tugas_akhir']) : false;
        // All conversion above precedes the writes below.
        if ($studentPatch !== []) $student->fill($studentPatch)->save();
        $this->upsert(StudentCourseIssue::class, $nim, $issues);
        $this->upsert(StudentSidangTingkat::class, $nim, $levels);
        if ($grade !== false) $this->upsert(StudentFinalProjectGrade::class, $nim, ['grade' => $grade]);
        if ($facts === [] && ($studentPatch !== [] || $issues !== [] || $levels !== [] || $grade !== false)) {
            // Marks a successful partial academic update without inventing facts.
            $facts['profile_present'] = true;
        }
        return $facts;
    }

    private function project(array $row, string $nim): bool
    {
        $patch = [];
        foreach (['judul_tugas_akhir' => 'thesis_title_id', 'pembimbing_1' => 'advisor_1', 'pembimbing_2' => 'advisor_2',
                     'penguji_1' => 'examiner_1', 'penguji_2' => 'examiner_2', 'waktu' => 'exam_time', 'tempat' => 'exam_location'] as $key => $column) {
            if (array_key_exists($key, $row)) $patch[$column] = $this->text($row[$key]);
        }
        if (array_key_exists('tanggal_sidang', $row)) {
            $date = $this->text($row['tanggal_sidang']);
            $patch['exam_date'] = null;
            if ($date !== null) {
                $format = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? 'Y-m-d'
                    : (str_contains($date, '/') ? 'd/m/Y' : 'd-m-Y');
                try {
                    $parsed = Carbon::createFromFormat('!'.$format, $date);
                    if ($parsed->format($format) !== $date) throw new InvalidArgumentException;
                    $patch['exam_date'] = $parsed->format('Y-m-d');
                } catch (Throwable) {
                    throw new InvalidArgumentException('Tanggal sidang harus berformat d/m/Y, d-m-Y atau Y-m-d yang valid.');
                }
            }
        }
        return $this->upsert(StudentFinalProject::class, $nim, $patch);
    }

    private function upsert(string $model, string $nim, array $patch): bool
    {
        if ($patch === []) return false;
        $model::updateOrCreate(['student_number' => $nim], $patch);
        return true;
    }

    private function courseList(mixed $value): ?array
    {
        if ($value === null) return null;
        if (is_array($value) && array_is_list($value)) return $value;
        if (!is_string($value)) throw new InvalidArgumentException('Daftar mata kuliah harus berupa teks, daftar atau null.');
        $value = trim($value);
        if ($value === '' || $value === '-') return [];
        // Keep commas inside descriptions such as "(D, Semester 3)" intact.
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value)), fn ($line) => $line !== ''));
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Field teks tidak valid.');
        return trim($value) === '' ? null : trim($value);
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) throw new InvalidArgumentException('Field angka tidak valid.');
        return (float) $value;
    }

    private function boolean(mixed $value): ?bool
    {
        if ($value === null) return null;
        if ($value === true || $value === 'Y') return true;
        if ($value === false || $value === 'N') return false;
        throw new InvalidArgumentException('Status sidang/foto harus Y, N, boolean, atau null.');
    }
}
