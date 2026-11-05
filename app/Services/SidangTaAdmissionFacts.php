<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEprt;
use App\Support\SidangTaAdmissionPolicy as Policy;

class SidangTaAdmissionFacts
{
    public function __construct(private SidangTaAcademicSnapshot $snapshots) {}

    public function forStudent(Student $student): array
    {
        // Re-query related data: never trust a previously loaded relation cache.
        $student = Student::whereKey($student->id)->firstOrFail();
        $level = $student->sidangTingkat()->first();
        $skpi = $student->skpi()->first();
        $issues = $student->courseIssues()->first();
        $tak = $student->tak()->where(function ($query) {
            $query->whereNull('test_date')->orWhereDate('test_date', '<=', today());
        })->first();
        $krs = $this->snapshots->ta2KrsStatus($student);
        $facts = [
            'level_2_passed' => $level?->tingkat_2,
            'ta2_registered' => $krs === 'eligible' ? true : null,
            'skpi_approved' => $this->skpiApproval($skpi?->raw_data ?? []),
            'tak_score' => $tak?->total_points,
            'passed_credits' => $issues?->credits_passed ?? $student->total_credits,
        ];
        $eprt = $this->eprtFacts($student);
        return ['facts' => $facts, 'academic' => Policy::academic($facts),
            'krs_status' => $krs, 'eprt_facts' => $eprt,
            'eprt' => Policy::eprt($eprt['score'], $eprt['confirmed_attempts'])];
    }

    /** Existing raw-data aliases; unknown/conflicting values are not approval. */
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

    private function eprtFacts(Student $student): array
    {
        // Category rows are component scores, not completed tests.
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
        // A high attempt number alone is not evidence of its missing earlier tests.
        $attempts = $complete ? count($byAttempt) : null;
        return ['score' => $score, 'confirmed_attempts' => $attempts];
    }
}

