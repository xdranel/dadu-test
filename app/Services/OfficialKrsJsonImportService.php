<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Support\OfficialKrsJsonNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class OfficialKrsJsonImportService
{
    public function import(array $rows): array
    {
        $normalizer = new OfficialKrsJsonNormalizer;
        // Apply batch-level duplicate checks to direct callers too.
        $rows = $normalizer->rows($rows);
        $summary = ['processed' => 0, 'skipped' => 0, 'mismatched' => 0, 'courses' => 0, 'removed' => 0, 'errors' => []];
        $periods = AcademicPeriod::query()->get()->keyBy(fn ($p) => $p->year.'|'.$p->semester);
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row)) throw new InvalidArgumentException('Baris mahasiswa harus berupa objek.');
                $input = $normalizer->normalize($row);
                if ($input['courses'] === []) {
                    throw new InvalidArgumentException('Daftar KRS kosong belum mempunyai konteks periode; data lama dipertahankan.');
                }
                $outcome = DB::transaction(function () use ($input, $periods) {
                    $student = Student::where('student_number', $input['student_number'])->lockForUpdate()->first();
                    if (!$student) return ['kind' => 'skipped', 'count' => 0, 'removed' => 0, 'note' => 'Mahasiswa belum terdaftar.'];
                    if (mb_strtolower(trim($student->name)) !== mb_strtolower($input['name'])) {
                        return ['kind' => 'mismatched', 'count' => 0, 'removed' => 0, 'note' => 'NIM dan nama tidak cocok.'];
                    }
                    $studySemester = filter_var($student->active_semester, FILTER_VALIDATE_INT);
                    if ($studySemester === false || $studySemester < 1 || $studySemester > 127) {
                        throw new InvalidArgumentException('Semester studi mahasiswa belum valid; term KRS tidak dipakai sebagai pengganti.');
                    }
                    // Resolve every source period before writing the student's enrollments.
                    $codesByPeriod = [];
                    foreach ($input['courses'] as $course) {
                        if (!$periods->has($course['academic_year'].'|'.$course['term'])) {
                            throw new InvalidArgumentException('Periode KRS belum terdaftar; buat/periksa periode lalu impor ulang.');
                        }
                        $period = $periods->get($course['academic_year'].'|'.$course['term']);
                        $codesByPeriod[$period->id][] = $course['course_code'];
                    }
                    foreach ($input['courses'] as $course) {
                        $period = $periods->get($course['academic_year'].'|'.$course['term']);
                        $existing = StudentCourse::where('student_number', $input['student_number'])
                            ->where('course_code', $course['course_code'])
                            ->where('academic_period_id', $period->id)->limit(2)->get();
                        if ($existing->count() > 1) {
                            throw new InvalidArgumentException('Ada beberapa KRS lama untuk mata kuliah/periode yang sama; periksa dahulu.');
                        }
                        $enrollment = $existing->first();
                        if (!$enrollment) {
                            $hasUnlinked = StudentCourse::where('student_number', $input['student_number'])
                                ->where('course_code', $course['course_code'])->where('academic_year', $course['academic_year'])
                                ->whereNull('academic_period_id')->exists();
                            if ($hasUnlinked) {
                                throw new InvalidArgumentException('KRS lama belum tertaut periode; pastikan periode riwayat dahulu tanpa menebak term.');
                            }
                            $enrollment = StudentCourse::where('student_number', $input['student_number'])
                                ->where('course_code', $course['course_code'])->where('academic_year', $course['academic_year'])
                                ->where('semester', $studySemester)->first();
                        }
                        if ($enrollment && $enrollment->academic_period_id !== null
                            && (int) $enrollment->academic_period_id !== (int) $period->id) {
                            throw new InvalidArgumentException('Kunci KRS lama dipakai periode lain; periksa semester studi tanpa menimpa riwayat.');
                        }
                        $enrollment ??= new StudentCourse;
                        $master = Course::where('course_code', $course['course_code'])
                            ->where('academic_year', $course['academic_year'])
                            ->where('semester_term', $course['term'] === 1 ? 'GANJIL' : 'GENAP')->first();
                        $enrollment->fill([
                            'student_number' => $input['student_number'], 'course_code' => $course['course_code'],
                            'course_name' => $course['course_name'], 'course_class' => $course['course_class'],
                            'lecturer_code' => $course['lecturer_code'], 'study_program' => $course['study_program'],
                            'lecturer_name' => !$enrollment->exists || $enrollment->lecturer_code !== $course['lecturer_code']
                                ? null : $enrollment->lecturer_name,
                            'academic_year' => $course['academic_year'], 'academic_period_id' => $period->id,
                            'semester' => $enrollment->exists ? $enrollment->semester : $studySemester,
                            'credits' => $enrollment->exists ? $enrollment->credits : $master?->credits,
                        ])->save();
                    }
                    $removed = 0;
                    foreach ($codesByPeriod as $periodId => $codes) {
                        $removed += StudentCourse::where('student_number', $input['student_number'])
                            ->where('academic_period_id', $periodId)->whereNotIn('course_code', $codes)->delete();
                    }
                    return ['kind' => 'processed', 'count' => count($input['courses']), 'removed' => $removed, 'note' => null];
                }, 3);
            } catch (InvalidArgumentException $exception) {
                $summary['skipped']++;
                $summary['errors'][] = 'Baris #'.$index.': '.$exception->getMessage();
                continue;
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = 'Baris #'.$index.': penyimpanan KRS gagal; perubahan mahasiswa dibatalkan.';
                Log::warning('Official KRS row rolled back', ['row' => $index, 'exception' => $exception::class]);
                continue;
            }
            $summary[$outcome['kind']]++;
            $summary['courses'] += $outcome['count'];
            $summary['removed'] += $outcome['removed'];
            if ($outcome['note'] !== null) $summary['errors'][] = 'Baris #'.$index.': '.$outcome['note'];
            if ($outcome['kind'] === 'processed') {
                try { Cache::forget('campus:'.$input['student_number']); }
                catch (Throwable $exception) {
                    $summary['errors'][] = 'Baris #'.$index.': KRS tersimpan, penyegaran cache belum berhasil.';
                    Log::warning('Official KRS cache refresh failed', ['row' => $index, 'exception' => $exception::class]);
                }
            }
        }
        return $summary;
    }
}
