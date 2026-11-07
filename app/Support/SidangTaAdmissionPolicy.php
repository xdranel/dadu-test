<?php

namespace App\Support;

class SidangTaAdmissionPolicy
{
    /** Facts must be normalized by the synchronization adapter, never by HTTP input. */
    public static function academic(array $facts): array
    {
        $gates = [];
        foreach (['level_2_passed' => 'Sidang tingkat 2', 'ta2_registered' => 'KRS Sidang TA 2',
                     'skpi_approved' => 'SKPI'] as $key => $label) {
            $value = $facts[$key] ?? null;
            $gates[$key] = ['label' => $label,
                'state' => $value === true ? 'passed' : ($value === false ? 'failed' : 'unknown')];
        }
        foreach (['gpa' => ['IPK minimal 2,00', 2], 'tak_score' => ['TAK minimal 60', 60], 'passed_credits' => ['SKS lulus minimal 120', 120]] as $key => [$label, $minimum]) {
            $value = self::number($facts[$key] ?? null);
            $gates[$key] = ['label' => $label,
                'state' => $value === null ? 'unknown' : ($value >= $minimum ? 'passed' : 'failed')];
        }
        $blockers = [];
        foreach ($gates as $key => $gate) {
            if ($gate['state'] !== 'passed') {
                $blockers[$key] = $gate['label'].($gate['state'] === 'unknown'
                        ? ': data belum tersedia atau belum terkonfirmasi.' : ': belum memenuhi syarat.');
            }
        }
        return ['gates' => $gates, 'blockers' => $blockers, 'academic_passed' => $blockers === []];
    }

    /** EPRT is separate from the six academic gates; waiver still needs a PDF. */
    public static function eprt(mixed $score, mixed $confirmedAttempts): array
    {
        $numericScore = self::number($score);
        if ($score !== null && $numericScore === null) {
            return ['mode' => 'unknown', 'requires_waiver' => false,
                'message' => 'Data nilai atau riwayat tes EPRT belum lengkap.'];
        }
        if ($numericScore !== null && $numericScore >= 450) {
            return ['mode' => 'automatic', 'requires_waiver' => false,
                'message' => 'Nilai EPRT memenuhi syarat.'];
        }
        $attempts = self::number($confirmedAttempts);
        if ($attempts === null || floor($attempts) !== $attempts) {
            return ['mode' => 'unknown', 'requires_waiver' => false,
                'message' => 'Data nilai atau riwayat tes EPRT belum lengkap.'];
        }
        if ($attempts >= 3) {
            return ['mode' => 'waiver', 'requires_waiver' => true,
                'message' => 'Unggah Surat Permakluman.'];
        }
        return ['mode' => 'retest', 'requires_waiver' => false, 'message' => 'Ikuti Test EPRT lagi'];
    }

    private static function number(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) && $number >= 0 ? $number : null;
    }
}
