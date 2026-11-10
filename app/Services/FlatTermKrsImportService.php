<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Support\SyncImportIssues;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/** Additive import for flat exports whose semester explicitly means academic term. */
class FlatTermKrsImportService
{
    public function preview(array $rows, bool $lock = false): array
    {
        $seen = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;
            $key = json_encode([is_scalar($row['nim'] ?? null) ? trim((string)$row['nim']) : null,
                is_scalar($row['kode_matakuliah'] ?? null) ? strtoupper(trim((string)$row['kode_matakuliah'])) : null,
                is_scalar($row['tahun_akademik'] ?? null) ? trim((string)$row['tahun_akademik']) : null,
                in_array($row['semester'] ?? null, [1, 2, '1', '2'], true) ? (int)$row['semester'] : ($row['semester'] ?? null)]);
            $seen[$key][] = $index;
        }
        $duplicates = [];
        foreach ($seen as $indexes) {
            if (count($indexes) > 1) foreach ($indexes as $index) $duplicates[$index] = true;
        }
        $plans = [];
        foreach ($rows as $index => $row) {
            try {
                if (isset($duplicates[$index])) throw new InvalidArgumentException('Mata kuliah berulang pada mahasiswa/periode yang sama.');
                $plans[] = $this->plan($row, $lock);
            } catch (InvalidArgumentException $exception) {
                $plans[] = ['action' => 'skip', 'message' => $exception->getMessage(),
                    'student_number' => is_array($row) ? SyncImportIssues::text($row['nim'] ?? null) : null];
            }
        }
        // The legacy database key omits academic_period_id. Two source terms
        // cannot safely create the same student/year/study-semester/course key.
        $keys = [];
        foreach ($plans as $index => $plan) {
            if ($plan['action'] === 'skip') continue;
            $values = $plan['values'];
            $key = json_encode([$values['student_number'], $values['academic_year'], $values['semester'], strtoupper($values['course_code'])]);
            $keys[$key][] = $index;
        }
        foreach ($keys as $indexes) {
            if (count($indexes) < 2) continue;
            foreach ($indexes as $index) $plans[$index] = ['action' => 'skip',
                'message' => 'Kunci semester studi bentrok antar term pada file yang sama; periksa riwayat sebelum mengimpor.',
                'student_number' => $plans[$index]['student_number']];
        }
        return $plans;
    }

    public function fingerprint(array $plans): string
    {
        return hash('sha256', json_encode($plans, JSON_THROW_ON_ERROR));
    }

    /** Recheck all planned identities under locks before performing the reviewed writes. */
    public function apply(array $rows, array $reviewed): array
    {
        return DB::transaction(function () use ($rows, $reviewed) {
            $plans = $this->preview($rows, true);
            if (!hash_equals($this->fingerprint($reviewed), $this->fingerprint($plans))) {
                throw new InvalidArgumentException('Data berubah sejak pratinjau. Unggah ulang untuk meninjau perubahan terbaru.');
            }
            $summary = ['processed' => 0, 'skipped' => 0, 'repaired' => 0, 'errors' => []];
            foreach ($plans as $index => $plan) {
                if ($plan['action'] === 'skip') {
                    $summary['skipped']++;
                    $summary['errors'][] = 'Baris #'.$index.': '.$plan['message'];
                    continue;
                }
                $enrollment = $plan['id'] === null ? new StudentCourse : StudentCourse::findOrFail($plan['id']);
                $enrollment->fill($plan['values'])->save();
                $summary['processed']++;
                if ($plan['action'] === 'repair') $summary['repaired']++;
            }
            DB::afterCommit(function () use ($plans) {
                foreach (array_unique(array_column(array_filter($plans, fn ($p) => $p['action'] !== 'skip'), 'student_number')) as $nim) {
                    try { Cache::forget('campus:'.$nim); }
                    catch (Throwable $exception) { Log::warning('Flat KRS cache refresh failed', ['exception' => $exception::class]); }
                }
            });
            return $summary;
        }, 3);
    }

    private function plan(mixed $row, bool $lock): array
    {
        if (!is_array($row) || array_is_list($row)) throw new InvalidArgumentException('Baris KRS harus berupa objek.');
        $nim = $this->text($row['nim'] ?? null, 15);
        if (!preg_match('/^\d{1,15}$/D', $nim)) throw new InvalidArgumentException('NIM KRS tidak valid.');
        $code = $this->text($row['kode_matakuliah'] ?? null, 20);
        if (!preg_match('/\A[A-Za-z0-9._\/-]+\z/', $code)) throw new InvalidArgumentException('Kode mata kuliah harus berupa huruf Latin, angka, atau pemisah titik/garis. Periksa kode sumber.');
        $name = $this->text($row['nama_matakuliah'] ?? null, 255);
        $class = $this->text($row['kelas_matakuliah'] ?? null, 255);
        $year = $this->text($row['tahun_akademik'] ?? null, 10);
        if (!preg_match('/^(20\d{2})\/(20\d{2})$/D', $year, $parts) || (int)$parts[2] !== (int)$parts[1] + 1) {
            throw new InvalidArgumentException('Tahun akademik harus dua tahun berurutan, misalnya 2026/2027.');
        }
        if (!in_array($row['semester'] ?? null, [1, 2, '1', '2'], true)) {
            throw new InvalidArgumentException('Term KRS harus 1 (Ganjil) atau 2 (Genap).');
        }
        $term = (int)$row['semester'];
        $credits = $row['sks'] ?? null;
        if ($credits !== null && (filter_var($credits, FILTER_VALIDATE_INT) === false || (int)$credits < 0 || (int)$credits > 127)) {
            throw new InvalidArgumentException('SKS harus angka bulat 0–127 atau null.');
        }
        $studentQuery = Student::where('student_number', $nim);
        if ($lock) $studentQuery->lockForUpdate();
        $student = $studentQuery->first();
        if (!$student) throw new InvalidArgumentException('Mahasiswa belum terdaftar; impor profil mahasiswa terlebih dahulu.');
        $study = filter_var($student->active_semester, FILTER_VALIDATE_INT);
        if ($study === false || $study < 1 || $study > 127) throw new InvalidArgumentException('Semester studi pada profil mahasiswa belum valid.');
        $periodQuery = AcademicPeriod::where('year', $year)->where('semester', $term);
        if ($lock) $periodQuery->lockForUpdate();
        $period = $periodQuery->first();
        if (!$period) throw new InvalidArgumentException('Periode akademik KRS belum terdaftar; buat periode pada pengaturan KRS lalu unggah ulang.');
        if (array_key_exists('academic_period_id', $row)
            && (filter_var($row['academic_period_id'], FILTER_VALIDATE_INT) === false
                || (int)$row['academic_period_id'] !== $period->id)) {
            throw new InvalidArgumentException('ID periode tidak cocok dengan tahun akademik dan term sumber.');
        }

        $query = StudentCourse::where('student_number', $nim)->where('course_code', $code)->where('academic_year', $year)->orderBy('id');
        if ($lock) $query->lockForUpdate();
        $candidates = $query->get();
        $linked = $candidates->where('academic_period_id', $period->id);
        $unlinked = $candidates->whereNull('academic_period_id');
        if ($linked->count() > 1 || $unlinked->count() > 1 || ($linked->isNotEmpty() && $unlinked->isNotEmpty())) {
            throw new InvalidArgumentException('KRS lama ambigu; beberapa baris cocok. Periksa riwayat sebelum memperbaiki tautan.');
        }
        $enrollment = $linked->first();
        $action = $enrollment ? 'update' : 'create';
        if (!$enrollment && $unlinked->isNotEmpty()) {
            $candidate = $unlinked->first();
            if ($candidate->course_class !== $class || (int)$candidate->semester !== $term) {
                throw new InvalidArgumentException('KRS lama belum tertaut dan tidak cocok dengan kelas/term sumber; periksa riwayat tanpa menebak.');
            }
            $enrollment = $candidate;
            $action = 'repair';
        }
        $storedStudy = $action === 'update' ? (int)$enrollment->semester : $study;
        if ($candidates->contains(fn ($c) => $c->id !== $enrollment?->id && (int)$c->semester === $storedStudy)) {
            throw new InvalidArgumentException('Kunci KRS bentrok dengan periode/riwayat lain; data lama dipertahankan.');
        }
        $values = ['student_number' => $nim, 'course_code' => $code, 'course_name' => $name, 'course_class' => $class,
            'academic_year' => $year, 'academic_period_id' => $period->id, 'semester' => $storedStudy,
            'lecturer_code' => $this->text($row['kode_dosen'] ?? null, 20, true),
            'lecturer_name' => $this->text($row['nama_dosen'] ?? null, 255, true),
            'credits' => $credits === null ? null : (int)$credits, 'study_program' => $student->study_program];
        return ['action' => $action, 'id' => $enrollment?->id, 'student_number' => $nim, 'period' => $period->label,
            'values' => $values, 'previous_semester' => $enrollment?->semester, 'before' => $candidates->map(fn ($c) => $c->getAttributes())->all(),
            'student_state' => [$student->id, $student->active_semester, $student->study_program],
            'period_state' => $period->getAttributes()];
    }

    private function text(mixed $value, int $max, bool $nullable = false): ?string
    {
        if ($nullable && ($value === null || $value === '')) return null;
        if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Field teks KRS tidak valid.');
        $value = trim((string)$value);
        if ($value === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('Field wajib KRS kosong atau terlalu panjang.');
        return $value;
    }
}
