<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentEprt;
use App\Support\SidangTaAdmissionPolicy as Policy;

class SidangTaAdmissionFacts
{
    public function __construct(private SidangTaAcademicSnapshot $snapshots) {}

    public function forStudent(Student $student): array
    {
        $student = Student::whereKey($student->id)->firstOrFail();
        $profile = StudentAcademicProfile::where('student_number', $student->student_number)->first();
        $krs = $this->snapshots->ta2KrsStatus($student);

        if ($profile !== null) {
            // Once an official profile exists, missing/null facts never borrow manual values.
            $groups = is_array($profile->groups) ? $profile->groups : [];
            $academic = is_array($groups['academic'] ?? null) ? $groups['academic'] : [];
            $skpi = is_array($groups['skpi'] ?? null) ? $groups['skpi'] : [];
            $tak = is_array($groups['tak'] ?? null) ? $groups['tak'] : [];
            $level = $academic['level_2_passed'] ?? null;
            $facts = [
                'level_2_passed' => is_bool($level) ? $level : null,
                'ta2_registered' => $krs === 'eligible' ? true : null,
                'skpi_approved' => array_key_exists('skpi', $groups) ? ($skpi['status'] ?? null) === 'Disetujui' : null,
                'tak_score' => $this->number($tak['score'] ?? null),
                'passed_credits' => $this->number($academic['passed_credits'] ?? null),
            ];
            $eprt = $this->officialLanguage($groups['language'] ?? null);
        } else {
            // Compatibility for existing nested-import fixtures and pre-import local data.
            $facts = $this->legacyFacts($student, $krs);
            $eprt = $this->legacyEprtFacts($student) + ['type' => null];
        }

        return ['facts' => $facts, 'academic' => Policy::academic($facts),
            'krs_status' => $krs, 'eprt_facts' => $eprt,
            'eprt' => Policy::eprt($eprt['score'], $eprt['confirmed_attempts'])];
    }

    private function officialLanguage(mixed $language): array
    {
        $unknown = ['score' => null, 'confirmed_attempts' => null, 'type' => null];
        if (!is_array($language)) return $unknown;
        foreach (['score', 'attempts', 'type', 'history'] as $key) {
            if (!array_key_exists($key, $language)) return $unknown;
        }
        $score = $this->number($language['score']);
        if ($language['score'] !== null && $score === null) return $unknown;
        $attempts = $language['attempts'];
        if ($attempts !== null && (!is_int($attempts) || $attempts < 0)) return $unknown;
        if ($language['type'] !== null && !is_string($language['type'])) return $unknown;
        if (!is_array($language['history']) || !array_is_list($language['history'])) return $unknown;
        foreach ($language['history'] as $entry) {
            if (!is_array($entry) || array_is_list($entry)) return $unknown;
        }

        // The exporter owns filtering/counting; history length is not a test count.
        return ['score' => $score, 'confirmed_attempts' => $attempts, 'type' => $language['type']];
    }

    private function number(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) return null;
        return (float) $value;
    }

    private function legacyFacts(Student $student, string $krs): array
    {
        $level = $student->sidangTingkat()->first();
        $skpi = $student->skpi()->first();
        $issues = $student->courseIssues()->first();
        $tak = $student->tak()->where(function ($query) {
            $query->whereNull('test_date')->orWhereDate('test_date', '<=', today());
        })->first();
        return [
            'level_2_passed' => $level?->tingkat_2,
            'ta2_registered' => $krs === 'eligible' ? true : null,
            'skpi_approved' => $this->skpiApproval($skpi?->raw_data ?? []),
            'tak_score' => $tak?->total_points,
            'passed_credits' => $issues?->credits_passed ?? $student->total_credits,
        ];
    }

    private function skpiApproval(array $raw): ?bool
    {
        $values = [];
        foreach (['status', 'approval_status', 'is_approved'] as $key) {
            if (!array_key_exists($key, $raw) || $raw[$key] === null) continue;
            $value = $raw[$key];
            if (is_bool($value)) {
                $values[] = $value;
                continue;
            }
            if (!is_string($value) && !is_int($value)) return null;
            $value = strtolower(trim((string) $value));
            if (in_array($value, ['1', 'true', 'approved', 'disetujui', 'lulus'], true)) {
                $values[] = true;
            } elseif (in_array($value, ['0', 'false', 'rejected', 'ditolak', 'pending', 'menunggu'], true)) {
                $values[] = false;
            } else {
                return null;
            }
        }
        if ($values === [] || in_array(true, $values, true) && in_array(false, $values, true)) return null;
        return $values[0];
    }

    private function legacyEprtFacts(Student $student): array
    {
        $rows = StudentEprt::where('student_number', $student->student_number)
            ->where(function ($query) { $query->whereNull('category')->orWhere('category', ''); })
            ->whereNotNull('total_score')->where('total_score', '>=', 0)
            ->where(function ($query) { $query->whereNull('test_date')->orWhereDate('test_date', '<=', today()); })
            ->get();
        $score = $rows->max('total_score');
        $byAttempt = [];
        $byDate = [];
        $complete = $rows->isNotEmpty();
        foreach ($rows as $row) {
            $attempt = $row->attempt_number;
            $date = $row->test_date?->toDateString();
            if (!is_int($attempt) || $attempt < 1 || $date === null) {
                $complete = false;
                continue;
            }
            if ((isset($byAttempt[$attempt]) && $byAttempt[$attempt] !== $date)
                || (isset($byDate[$date]) && $byDate[$date] !== $attempt)) {
                $complete = false;
            }
            $byAttempt[$attempt] = $date;
            $byDate[$date] = $attempt;
        }
        return ['score' => $score, 'confirmed_attempts' => $complete ? count($byAttempt) : null];
    }
}
