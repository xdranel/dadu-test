<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentCourse;

class SidangTaAcademicSnapshot
{
    public function forStudent(Student $student, ?array $admission = null): array
    {
        $number = $student->student_number;
        $ta2Status = $this->ta2KrsStatus($student);
        $project = $student->finalProject;
        $admission ??= app(SidangTaAdmissionFacts::class)->forStudent($student);
        $language = $admission['eprt_facts'];
        $profile = \App\Models\StudentAcademicProfile::where('student_number', $number)->first();
        $groups = $profile?->groups ?? [];
        $bpp = $profile ? ($groups['bpp'] ?? []) : ['status' => $student->bppPayment?->status];
        $bppStatus = is_scalar($bpp['status'] ?? null) ? trim((string) $bpp['status']) : '';
        $bppStatus = match (strtolower($bppStatus)) {
            'paid', 'lunas' => 'Lunas', 'unpaid', 'belum lunas', 'belum_lunas' => 'Belum lunas',
            '' => 'Belum tersedia', default => $bppStatus,
        };
        $approval = $groups['sidang_approval'] ?? [];
        $approvalStatus = is_scalar($approval['status'] ?? null) && trim((string) $approval['status']) !== ''
            ? (string) $approval['status'] : 'Belum tersedia';
        $facts = $admission['facts'];

        return [
            'student_number' => $number, 'student_name' => $student->name,
            'ta2_krs_status' => $ta2Status,
            'ta2_krs_label' => match ($ta2Status) {
                'eligible' => 'Terdaftar',
                'unconfirmed' => 'Belum terkonfirmasi',
                default => 'Belum tersedia',
            },
            'study_program' => $student->study_program ?? '-', 'semester' => $student->active_semester ?? '-',
            'gpa' => $student->gpa, 'passed_credits' => $facts['passed_credits'],
            'sidang_tingkat_2' => match ($facts['level_2_passed']) { true => 'Lulus', false => 'Belum lulus', default => 'Belum tersedia' },
            'ta_title' => $project?->thesis_title_id ?? '-', 'ta_title_en' => $project?->thesis_title_en ?? '-',
            'advisor_1' => $project?->advisor_1 ?? '-', 'advisor_2' => $project?->advisor_2 ?? '-',
            'examiner_1' => $project?->examiner_1 ?? '-', 'examiner_2' => $project?->examiner_2 ?? '-',
            'examiner_3' => $project?->examiner_3 ?? '-',
            'exam_date' => $project?->exam_date?->format('d/m/Y') ?? '-',
            'exam_time' => $project?->exam_time ?? '-', 'exam_location' => $project?->exam_location ?? '-',
            'tak_score' => $facts['tak_score'], 'eprt_score' => $language['score'], 'eprt_facts' => $language,
            'bpp_status' => $bppStatus, 'bpp_period' => $bpp['period'] ?? null,
            'bpp_periods' => $profile?->bpp_periods ?? [],
            'sidang_approval_status' => $approvalStatus,
            'sidang_approval_approved' => $approval['approved'] ?? null,
            'admission' => $admission,
            'skpi_status' => match ($facts['skpi_approved']) { true => 'Disetujui', false => 'Belum memenuhi', default => 'Belum tersedia' },
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
            if (in_array($normalized, ['TUGAS AKHIR', 'TUGAS AKHIR 2', 'TUGAS AKHIR II', 'SIDANG TA 2', 'TA 2', 'TA-2', 'FINAL THESIS 2'], true)) {
                return 'eligible';
            }
        }
        // Match the whole normalized name; proposals/TA1/TA20 are not accepted.
        return 'unconfirmed';
    }

}
