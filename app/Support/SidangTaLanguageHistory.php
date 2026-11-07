<?php

namespace App\Support;

/** Normalizes imported language facts without converting scores or inventing test dates/counts. */
class SidangTaLanguageHistory
{
    public static function facts(mixed $language): array
    {
        $unknown = ['score' => null, 'confirmed_attempts' => null, 'type' => null, 'history' => [],
            'scale_identified' => false];
        if (!is_array($language)) return $unknown;
        foreach (['score', 'attempts', 'type', 'history'] as $key) {
            if (!array_key_exists($key, $language)) return $unknown;
        }
        $score = self::number($language['score']);
        $attempts = $language['attempts'];
        if (($language['score'] !== null && $score === null)
            || ($attempts !== null && (!is_int($attempts) || $attempts < 0))
            || ($language['type'] !== null && !is_string($language['type']))
            || !is_array($language['history']) || !array_is_list($language['history'])) return $unknown;

        $history = [];
        $candidates = [];
        $authoritative = false;
        $scale = self::scale($language['type']);
        foreach ($language['history'] as $entry) {
            if (!is_array($entry) || array_is_list($entry)) return $unknown;
            $row = self::row($entry);
            $history[] = $row;
            // Subscores and unrelated test scales never affect the selected main score.
            $category = $entry['category'] ?? $entry['kategori'] ?? '';
            if (!is_string($category)) continue;
            $category = strtolower(trim($category));
            // Other test types retain the established official aggregate policy.
            if ($scale !== 'eprt' || self::scale($row['type']) !== $scale || $row['score'] === null
                || !in_array($category, ['', 'total', 'main', 'overall', 'utama'], true)) continue;
            $hasValidity = false;
            foreach (['status_berlaku', 'validity', 'kedaluwarsa', 'expires_at', 'expiry_date'] as $key) {
                if (isset($entry[$key]) && $entry[$key] !== '') $hasValidity = true;
            }
            $testRaw = $entry['tanggal_tes'] ?? $entry['test_date'] ?? null;
            if ($testRaw !== null && $testRaw !== ''
                && ($row['test_date'] === null || $row['test_date'] > today()->toDateString())) $hasValidity = true;
            $authoritative = $authoritative || $hasValidity;
            if ($row['validity'] === 'valid') $candidates[] = $row['score'];
        }
        if ($authoritative) $score = $candidates === [] ? null : max($candidates);
        return ['score' => $score, 'confirmed_attempts' => $attempts,
            'type' => $language['type'], 'history' => $history, 'scale_identified' => $scale !== null];
    }

    public static function row(array $entry): array
    {
        $type = $entry['jenis_tes'] ?? $entry['type'] ?? null;
        $testRaw = $entry['tanggal_tes'] ?? $entry['test_date'] ?? null;
        $expiryRaw = $entry['kedaluwarsa'] ?? $entry['expires_at'] ?? $entry['expiry_date'] ?? null;
        $test = self::date($testRaw);
        $expiry = self::date($expiryRaw);
        $statusRaw = $entry['status_berlaku'] ?? $entry['validity'] ?? null;
        $status = is_string($statusRaw) ? strtolower(trim($statusRaw)) : null;
        $status = match ($status) {
            'berlaku', 'valid', 'active' => 'valid',
            'kedaluwarsa', 'kadaluarsa', 'expired', 'tidak berlaku' => 'expired',
            default => null,
        };
        $validity = $expiry === null ? $status : ($expiry < today()->toDateString() ? 'expired' : 'valid');
        if (($testRaw !== null && $testRaw !== '' && $test === null)
            || ($expiryRaw !== null && $expiryRaw !== '' && $expiry === null)
            || ($statusRaw !== null && $statusRaw !== '' && $status === null)
            || ($status !== null && $expiry !== null && $status !== $validity)
            || ($test !== null && $test > today()->toDateString())
            || ($test !== null && $expiry !== null && $test > $expiry)) $validity = 'unknown';
        return ['type' => is_string($type) ? $type : null,
            'score' => self::number($entry['nilai'] ?? $entry['score'] ?? $entry['total_score'] ?? null),
            'test_date' => $test, 'expires_at' => $expiry, 'validity' => $validity ?? 'unknown',
            'source' => $entry];
    }

    private static function scale(?string $type): ?string
    {
        if ($type === null || trim($type) === '') return null;
        $type = strtolower(trim(preg_replace('/\s+/', ' ', $type)));
        return match ($type) {
            'online eprt', 'eprt' => 'eprt',
            'itp toefl', 'toefl itp' => 'toefl-itp',
            'ibt toefl', 'toefl ibt' => 'toefl-ibt',
            'ecct', 'online ecct' => 'ecct',
            'toeic', 'ielts' => $type,
            default => null,
        };
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        $value = strtolower(trim($value));
        $months = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5,
            'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10,
            'november' => 11, 'desember' => 12];
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)) {
            [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } elseif (preg_match('/\A(\d{1,2})\s+([a-z]+)\s+(\d{4})\z/', $value, $parts)
            && isset($months[$parts[2]])) {
            [$year, $month, $day] = [(int) $parts[3], $months[$parts[2]], (int) $parts[1]];
        } else return null;
        if (!checkdate($month, $day, $year)) return null;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function number(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) return null;
        return (float) $value;
    }
}
