<?php

namespace App\Services;

use App\Contracts\CampusDataGrabServiceInterface;
use App\Exceptions\CampusDataGrabException;
use App\Models\AcademicPeriod;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\StudentTak;

/** Reads the campus profile populated by the admin's JSON import. */
class ImportedCampusDataGrabService implements CampusDataGrabServiceInterface
{
    public function getStudentGraduationData(string $studentNumber): array
    {
        $student = Student::with([
            'finalProject', 'tak', 'sidangTingkat', 'basilaApproval',
            'finalProjectGrade', 'courseIssues', 'graduationPayment', 'bppPayment', 'skpi',
        ])->where('student_number', $studentNumber)->first();

        if (!$student) {
            throw CampusDataGrabException::studentNotFound($studentNumber);
        }

        $tak = StudentTak::where('student_number', $studentNumber)
            ->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            })
            ->orderByDesc('total_points')
            ->orderByRaw("COALESCE(expiry_date, '9999-12-31') DESC")
            ->first();

        $activePeriod = AcademicPeriod::current();
        $ta2Rows = StudentCourse::where('student_number', $studentNumber)
            ->where(function ($query) {
                $query->where('course_code', 'LIKE', '%TA2%')
                    ->orWhere('course_code', 'LIKE', '%TA-2%')
                    ->orWhere('course_code', 'LIKE', '%TA 2%')
                    ->orWhere('course_name', 'LIKE', '%TA 2%')
                    ->orWhere('course_name', 'LIKE', '%TA-2%')
                    ->orWhere('course_name', 'LIKE', '%TUGAS AKHIR 2%')
                    ->orWhere('course_name', 'LIKE', '%TUGAS AKHIR II%')
                    ->orWhere('course_name', 'LIKE', '%FINAL THESIS 2%');
            });
        // KRS `semester` is the student's study semester (e.g. 4/8), not
        // the odd/even term stored by AcademicPeriod. Match via the period FK.
        $hasTa2 = $activePeriod
            ? (clone $ta2Rows)->where('academic_period_id', $activePeriod->id)->exists()
            : false;
        $ta2KrsStatus = !$activePeriod
            ? 'no_active_period'
            : ($hasTa2
                ? 'eligible'
                : ((clone $ta2Rows)->whereNull('academic_period_id')->exists()
                    ? 'period_unlinked'
                    : ($ta2Rows->exists() ? 'other_period' : 'ta2_missing')));

        $issues = $student->courseIssues;
        $skpi = $student->skpi?->raw_data ?? [];
        $skpiApproval = $skpi['status'] ?? $skpi['approval_status'] ?? $skpi['is_approved'] ?? null;
        $normalizedSkpiApproval = is_scalar($skpiApproval)
            ? strtolower(trim((string) $skpiApproval))
            : '';
        $skpiApproval = in_array($skpiApproval, [true, 1], true)
            || in_array($normalizedSkpiApproval, ['1', 'true', 'lulus', 'disetujui', 'approved'], true)
            ? 'approved'
            : $normalizedSkpiApproval;
        $advisor1 = $student->finalProject?->advisor_1;
        $advisor2 = $student->finalProject?->advisor_2;
        $examiner1 = $student->finalProject?->examiner_1;
        $examiner2 = $student->finalProject?->examiner_2;
        $examiner3 = $student->finalProject?->examiner_3;

        return [
            'student_number' => $student->student_number,
            'student_name' => $student->name,
            'student_email' => $student->email,
            'study_program' => $student->study_program,
            'academic_advisor_name' => $student->academic_advisor,
            'academic_advisor_code' => null,
            'advisor_1_name' => $advisor1,
            'advisor_1_code' => $this->lecturerCode($advisor1),
            'advisor_2_name' => $advisor2,
            'advisor_2_code' => $this->lecturerCode($advisor2),
            'examiner_1_name' => $examiner1,
            'examiner_1_code' => $this->lecturerCode($examiner1),
            'examiner_2_name' => $examiner2,
            'examiner_2_code' => $this->lecturerCode($examiner2),
            'examiner_3_name' => $examiner3,
            'examiner_3_code' => $this->lecturerCode($examiner3),
            'exam_date' => $student->finalProject?->exam_date?->format('Y-m-d'),
            'tak_score' => $tak?->total_points,
            'eprt_score' => StudentEprt::where('student_number', $studentNumber)->max('total_score'),
            'gpa' => $student->gpa,
            'passed_credits' => $issues?->credits_passed ?? $student->total_credits,
            'remaining_credits_db' => $issues?->credits_remaining,
            'active_semester_db' => $student->active_semester,
            'payment_status' => $student->graduationPayment?->status,
            'bpp_payment_status' => $student->bppPayment?->status,
            'thesis_grade_index' => $student->finalProjectGrade?->grade,
            'has_ta2_in_krs' => $hasTa2,
            'ta2_krs_status' => $ta2KrsStatus,
            'ibasila_photo_approval_status' => $student->basilaApproval?->status
                ?? ($student->basilaApproval?->photo_approval ? 'approved' : 'pending'),
            'thesis_title_id' => $student->finalProject?->thesis_title_id,
            'thesis_title_en' => $student->finalProject?->thesis_title_en,
            'level_3_exam_status' => $student->sidangTingkat?->tingkat_3 ? 'passed' : 'not_passed',
            'failed_courses' => $this->normalizeCourses($issues?->failed_courses),
            'retaken_courses' => $this->normalizeCourses($issues?->retaking_courses),
            'skpi_approval_status' => $skpiApproval,
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getStudentDataPartial(string $studentNumber, array $fields): array
    {
        return array_intersect_key($this->getStudentGraduationData($studentNumber), array_flip($fields));
    }

    private function normalizeCourses(?array $courses): array
    {
        return collect($courses ?? [])->map(function ($course) {
            if (is_string($course)) {
                return ['course_code' => $course, 'course_name' => $course, 'attempt' => 1];
            }

            return [
                'course_code' => $course['course_code'] ?? $course['kode'] ?? $course['kode_matakuliah'] ?? '-',
                'course_name' => $course['course_name'] ?? $course['nama'] ?? $course['nama_matakuliah'] ?? '-',
                'attempt' => $course['attempt'] ?? $course['percobaan'] ?? 1,
            ];
        })->all();
    }

    private function lecturerCode(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        return Lecturer::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->value('lecturer_code');
    }
}
