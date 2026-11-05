<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentTak;
use App\Models\StudentEprt;

class SidangTaAcademicSnapshot
{
    public function forStudent(Student $student): array
    {
        $number = $student->student_number;
        $ta2Status = $this->ta2KrsStatus($student);
        $project = $student->finalProject;
        $tak = $student->tak ?? StudentTak::where('student_number', $number)->latest('test_date')->first();
        $eprt = $student->eprts()->orderByDesc('test_date')->first()
            ?? StudentEprt::where('student_number', $number)->orderByDesc('test_date')->first();
        $skpi = $student->skpi?->raw_data ?? [];
        $status = $skpi['status'] ?? $skpi['approval_status'] ?? $skpi['is_approved'] ?? 'Belum tersedia';
        if ($status === true) $status = 'Disetujui';

        return [
            'student_number' => $number, 'student_name' => $student->name,
            'ta2_krs_status' => $ta2Status,
            'ta2_krs_label' => match ($ta2Status) {
                'eligible' => 'Terdaftar',
                'unconfirmed' => 'Belum terkonfirmasi',
                default => 'Belum tersedia',
            },
            'study_program' => $student->study_program ?? '-', 'semester' => $student->active_semester ?? '-',
            'gpa' => $student->gpa, 'passed_credits' => $student->total_credits,
            'sidang_tingkat_2' => $student->sidangTingkat?->tingkat_2 ? 'Lulus' : 'Belum lulus / belum tersedia',
            'ta_title' => $project?->thesis_title_id ?? '-', 'ta_title_en' => $project?->thesis_title_en ?? '-',
            'advisor_1' => $project?->advisor_1 ?? '-', 'advisor_2' => $project?->advisor_2 ?? '-',
            'examiner_1' => $project?->examiner_1 ?? '-', 'examiner_2' => $project?->examiner_2 ?? '-',
            'examiner_3' => $project?->examiner_3 ?? '-',
            'exam_date' => $project?->exam_date?->format('d/m/Y') ?? '-',
            'exam_time' => $project?->exam_time ?? '-', 'exam_location' => $project?->exam_location ?? '-',
            'tak_score' => $tak?->total_points, 'eprt_score' => $eprt?->total_score,
            'bpp_status' => $student->bppPayment?->status ?? 'Belum tersedia',
            'skpi_status' => is_scalar($status) ? (string) $status : 'Tersedia',
            'skpi_tak' => $student->tak ? 'TAK tersedia' : 'TAK belum tersedia',
            'level_3_status' => $student->sidangTingkat?->tingkat_3 ? 'Lulus' : 'Belum lulus / belum tersedia',
            'ibasila_status' => $student->basilaApproval?->status ?? 'Belum tersedia',
            'student_email' => $student->email,
            'academic_source_updated_at' => $student->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public function ta2KrsStatus(Student $student): string
    {
        $periods = AcademicPeriod::query()->where('is_active', true)->pluck('id');
        if ($periods->count() !== 1) return 'unknown';
        $names = StudentCourse::query()
            ->where('student_number', $student->student_number)
            ->where('academic_period_id', $periods->first())->pluck('course_name');
        if ($names->isEmpty()) return 'unknown';
        foreach ($names as $name) {
            $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
            if (in_array($normalized, ['TUGAS AKHIR 2', 'TUGAS AKHIR II', 'SIDANG TA 2', 'TA 2', 'TA-2', 'FINAL THESIS 2'], true)) {
                return 'eligible';
            }
        }
        // Generic names await official mapping; do not report them as passed.
        return 'unconfirmed';
    }
}
