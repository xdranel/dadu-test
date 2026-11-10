<?php

namespace App\Services;

use App\Models\{Student, StudentAcademicDataEdit, StudentAcademicProfile, StudentBasilaApproval, StudentBppPayment,
    StudentCourseIssue, StudentFinalProject, StudentFinalProjectGrade, StudentGraduationPayment, StudentSidangTingkat,
    StudentSkpi, StudentTak, StudentActivity, User};
use Illuminate\Support\Facades\{Cache, DB, Log};
use Illuminate\Validation\ValidationException;
use Throwable;

/** Manual corrections of live academic records, without editing source files or submitted snapshots. */
class StudentAcademicDataEditor
{
    private const MODELS = [
        'profile' => StudentAcademicProfile::class, 'project' => StudentFinalProject::class,
        'levels' => StudentSidangTingkat::class, 'issues' => StudentCourseIssue::class,
        'skpi' => StudentSkpi::class, 'basila' => StudentBasilaApproval::class,
        'grade' => StudentFinalProjectGrade::class, 'bpp' => StudentBppPayment::class,
        'graduation_payment' => StudentGraduationPayment::class,
    ];

    public function prepare(Student $student): array
    {
        return DB::transaction(function () use ($student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            return ['student' => $student, 'values' => $this->values($student),
                'editVersion' => $this->fingerprint($this->snapshot($student))];
        }, 3);
    }

    private function snapshot(Student $student): array
    {
        $state = ['student' => $student->getAttributes()];
        foreach (self::MODELS as $key => $model) {
            $state[$key] = $model::where('student_number', $student->student_number)->orderBy('id')->get()
                ->map(fn ($row) => $row->getAttributes())->all();
        }
        $state['tak'] = StudentTak::where('student_number', $student->student_number)->orderBy('id')->get()
            ->map(fn ($row) => $row->getAttributes())->all();
        $state['activities'] = StudentActivity::where('student_number', $student->student_number)->orderBy('id')->get()->map(fn ($row) => $row->getAttributes())->all();
        return $state;
    }

    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function values(Student $student): array
    {
        $student->load(['sidangTingkat', 'finalProject', 'courseIssues', 'skpi', 'basilaApproval', 'bppPayment', 'graduationPayment', 'finalProjectGrade']);
        $profile = StudentAcademicProfile::where('student_number', $student->student_number)->first();
        $groups = $profile?->groups ?? [];
        $facts = app(SidangTaAdmissionFacts::class)->forStudent($student);
        $values = ['edit_note' => null];
        foreach (range(1, 4) as $level) {
            $value = $student->sidangTingkat?->{'tingkat_'.$level};
            if ($level === 2 && $profile !== null) $value = $groups['academic']['level_2_passed'] ?? null;
            $values['sidang_tingkat_'.$level] = $value === null ? null : ($value ? 'Y' : 'N');
        }
        foreach (['thesis_title_id', 'thesis_title_en', 'advisor_1', 'advisor_2', 'examiner_1', 'examiner_2', 'exam_time', 'exam_location'] as $field) {
            $values[$field] = $student->finalProject?->$field;
        }
        $values['exam_date'] = $student->finalProject?->exam_date?->toDateString();
        $values['ta_grade'] = $student->finalProjectGrade?->grade;
        $values['tak_score'] = $facts['facts']['tak_score'];
        if ($profile === null && $values['tak_score'] === null && StudentActivity::where('student_number', $student->student_number)->exists()) {
            $values['tak_score'] = (float) StudentActivity::where('student_number', $student->student_number)->sum('points');
        }
        $values['basila_status'] = $student->basilaApproval?->status;
        $values['credits_passed'] = $student->courseIssues?->credits_passed ?? $student->total_credits;
        $values['credits_remaining'] = $student->courseIssues?->credits_remaining;
        foreach (['retaking_courses', 'failed_courses'] as $field) {
            $items = $student->courseIssues?->$field;
            $values[$field] = $items === null ? null : ($items === [] ? '-' : implode("\n", array_filter($items, 'is_string')));
        }
        $values['graduation_payment_status'] = $student->graduationPayment?->status;
        $raw = $profile !== null && !array_key_exists('skpi', $groups) ? [] : ($student->skpi?->raw_data ?? []);
        $values['skpi_status'] = $profile !== null ? ($groups['skpi']['status'] ?? null)
            : ($raw['status_skpi'] ?? $raw['status'] ?? match ($facts['facts']['skpi_approved']) { true => 'Disetujui', false => 'Belum Disetujui', default => null });
        foreach (range(1, 3) as $part) $values['skpi_status_'.$part] = $raw['skpi_status_'.$part] ?? null;
        $values['skpi_approval'] = $values['skpi_status'] === null ? null
            : ($raw['approval_status'] ?? ($values['skpi_status'] === 'Disetujui' ? 'approved' : 'rejected'));
        // Canonicalize recognized legacy approval aliases without revoking an existing pass.
        if ($profile === null && $facts['facts']['skpi_approved'] !== null) {
            $approved = $facts['facts']['skpi_approved'];
            $values['skpi_status'] = $approved ? 'Disetujui' : 'Belum Disetujui';
            $values['skpi_approval'] = $approved ? 'approved'
                : (($raw['approval_status'] ?? null) === 'pending' || ($raw['status'] ?? null) === 'pending' ? 'pending' : 'rejected');
        }
        $values['sidang_approval_status'] = $groups['sidang_approval']['status'] ?? null;
        $periods = $profile?->bpp_periods ?? [];
        $current = $profile !== null ? ($groups['bpp'] ?? []) : ['status' => $student->bppPayment?->status, 'period' => null];
        if (($current['status'] ?? null) !== null && !array_key_exists($current['period'] ?? '', $periods)) {
            $periods[$current['period'] ?? ''] = $current;
        }
        krsort($periods, SORT_STRING);
        $values['bpp_payments'] = []; $values['bpp_current_index'] = -1;
        foreach ($periods as $period => $payment) {
            if (!is_array($payment)) continue;
            $index = count($values['bpp_payments']);
            $values['bpp_payments'][] = ['period' => $period === '' ? null : (string) $period, 'status' => $payment['status'] ?? null];
            if (array_key_exists('status', $current) && ($current['period'] ?? '') === (string) $period) $values['bpp_current_index'] = $index;
        }
        return $values;
    }

    /** Preserve known legacy facts when the first manual correction creates a profile. */
    private function legacyGroups(Student $student): array
    {
        $admission = app(SidangTaAdmissionFacts::class)->forStudent($student);
        $facts = $admission['facts']; $language = $admission['eprt_facts'];
        return ['academic' => ['gpa' => $facts['gpa'], 'passed_credits' => $facts['passed_credits'], 'level_2_passed' => $facts['level_2_passed']],
            'tak' => ['score' => $facts['tak_score']],
            'language' => ['score' => $language['score'], 'attempts' => $language['confirmed_attempts'], 'type' => 'EPrT',
                'history' => array_map(fn ($row) => $row['source'], $language['history'])]];
    }

    public function save(Student $student, array $values, User $actor): void
    {
        $number = DB::transaction(function () use ($student, $values, $actor) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $nim = $student->student_number; $before = $this->snapshot($student);
            if (!hash_equals($this->fingerprint($before), $values['edit_version'])) {
                throw ValidationException::withMessages(['edit_version' => 'Data telah berubah sejak form dibuka. Muat ulang data terbaru sebelum menyimpan koreksi.']);
            }
            $previousValues = $this->values($student);
            $profile = StudentAcademicProfile::where('student_number', $nim)->first();
            $groups = $profile?->groups ?? $this->legacyGroups($student);
            $profile ??= new StudentAcademicProfile(['student_number' => $nim]);
            $academic = $groups['academic'] ?? [];
            $academic['passed_credits'] = $values['credits_passed'] === null ? null : (int) $values['credits_passed'];
            $academic['level_2_passed'] = $values['sidang_tingkat_2'] === null ? null : $values['sidang_tingkat_2'] === 'Y';
            $groups['academic'] = $academic;
            $student->total_credits = $academic['passed_credits']; $student->save();
            $levels = [];
            foreach (range(1, 4) as $level) $levels['tingkat_'.$level] = $values['sidang_tingkat_'.$level] === null ? null : $values['sidang_tingkat_'.$level] === 'Y';
            if (array_filter(array_intersect_key($values, array_flip(array_map(fn ($i) => 'sidang_tingkat_'.$i, range(1, 4)))), fn ($v) => $v !== null) !== []
                || StudentSidangTingkat::where('student_number', $nim)->exists()) $this->upsert(StudentSidangTingkat::class, $nim, $levels);
            $project = array_intersect_key($values, array_flip(['thesis_title_id', 'thesis_title_en', 'advisor_1', 'advisor_2', 'examiner_1', 'examiner_2', 'exam_date', 'exam_time', 'exam_location']));
            $this->upsert(StudentFinalProject::class, $nim, $project);
            $this->upsert(StudentFinalProjectGrade::class, $nim, ['grade' => $values['ta_grade']]);
            $tak = $values['tak_score'] === null ? null : (float) $values['tak_score'];
            $groups['tak'] = array_replace($groups['tak'] ?? [], ['score' => $tak]);
            if ($tak !== null || StudentTak::where('student_number', $nim)->whereNull('test_date')->exists()) {
                StudentTak::updateOrCreate(['student_number' => $nim, 'test_date' => null], ['total_points' => $tak, 'expiry_date' => null]);
            }
            if ($values['basila_status'] !== null || StudentBasilaApproval::where('student_number', $nim)->exists()) {
                $status = $values['basila_status'];
                $this->upsert(StudentBasilaApproval::class, $nim, ['status' => $status, 'photo_approval' => $status === null ? null : $status === 'approved']);
            }
            $this->upsert(StudentCourseIssue::class, $nim, [
                'credits_passed' => $academic['passed_credits'],
                'credits_remaining' => $values['credits_remaining'] === null ? null : (int) $values['credits_remaining'],
                'retaking_courses' => $this->courses($values['retaking_courses']), 'failed_courses' => $this->courses($values['failed_courses']),
            ]);
            $previousPayments = $profile->bpp_periods ?? [];
            $payments = []; $current = null;
            foreach ($values['bpp_payments'] as $index => $payment) {
                $period = $payment['period'] ?? null; $status = $payment['status'] ?? null;
                if ($period === null && $status === null) continue;
                $previousPeriod = $previousValues['bpp_payments'][$index]['period'] ?? '';
                $metadata = $previousPayments[$period ?? ''] ?? $previousPayments[$previousPeriod] ?? [];
                $item = array_replace($metadata, ['period' => $period, 'status' => $status]);
                $payments[$period ?? ''] = $item;
                if ((int) $values['bpp_current_index'] === $index) $current = $item;
            }
            $groups['bpp'] = $current ?? ['status' => null, 'period' => null];
            $this->upsert(StudentBppPayment::class, $nim, ['status' => $current['status'] ?? null]);
            if ($values['graduation_payment_status'] !== null || StudentGraduationPayment::where('student_number', $nim)->where('payment_type', 'wisuda')->exists()) {
                StudentGraduationPayment::updateOrCreate(['student_number' => $nim, 'payment_type' => 'wisuda'], ['status' => $values['graduation_payment_status']]);
            }
            $raw = StudentSkpi::where('student_number', $nim)->first()?->raw_data ?? [];
            $raw['status_skpi'] = $values['skpi_status']; $raw['approval_status'] = $values['skpi_approval'];
            foreach (range(1, 3) as $part) $raw['skpi_status_'.$part] = $values['skpi_status_'.$part];
            if (array_filter($raw, fn ($v) => $v !== null) !== [] || StudentSkpi::where('student_number', $nim)->exists()) $this->upsert(StudentSkpi::class, $nim, ['raw_data' => $raw]);
            if ($values['skpi_status'] !== null || array_key_exists('skpi', $groups)) {
                $groups['skpi'] = array_replace($groups['skpi'] ?? [], ['status' => $values['skpi_status'], 'approved' => $values['skpi_status'] === null ? null : $values['skpi_status'] === 'Disetujui']);
            }
            $groups['sidang_approval'] = array_replace($groups['sidang_approval'] ?? [], ['status' => $values['sidang_approval_status'],
                'approved' => $values['sidang_approval_status'] === null ? null : $values['sidang_approval_status'] === 'APPROVED']);
            $profile->fill(['groups' => $groups, 'bpp_periods' => $payments])->save();
            StudentAcademicDataEdit::create(['student_number' => $nim, 'user_id' => $actor->id,
                'before_data' => $before, 'after_data' => $this->snapshot($student->fresh()), 'note' => $values['edit_note'] ?? null]);
            return $nim;
        }, 3);
        try { Cache::forget('campus:'.$number); }
        catch (Throwable $exception) { Log::warning('Academic correction saved; cache refresh failed', ['student_number' => $number, 'exception' => $exception::class]); }
    }

    private function upsert(string $model, string $nim, array $values): void
    {
        if (array_filter($values, fn ($value) => $value !== null) !== [] || $model::where('student_number', $nim)->exists()) {
            $model::updateOrCreate(['student_number' => $nim], $values);
        }
    }

    private function courses(?string $text): ?array
    {
        if ($text === null || trim($text) === '') return null;
        if (trim($text) === '-') return [];
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn ($v) => $v !== ''));
    }
}
