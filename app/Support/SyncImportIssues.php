<?php

namespace App\Support;

final class SyncImportIssues
{
    public static function fromErrors(array $errors, array $rows, array $locations = [], ?string $module = null): array
    {
        $details = [];
        $rows = array_values($rows);
        foreach ($errors as $error) {
            $index = null;
            $parsedNim = null;
            if (preg_match('/^Baris #(\d+):\s*/u', $error, $match)) {
                $index = (int) $match[1];
            } elseif (preg_match('/^Baris NIM ([^:]+):/u', $error, $match)) {
                $parsedNim = trim($match[1]);
                // A repeated identity cannot safely identify one source record.
                $matches = array_keys(array_filter($rows, fn ($row) => is_array($row)
                    && self::text($row['nim'] ?? $row['student_number'] ?? null) === trim($match[1])));
                if (count($matches) === 1) $index = $matches[0];
            }
            $row = $index !== null && is_array($rows[$index] ?? null) ? $rows[$index] : [];
            $nim = $module === 'course' ? null : self::text($row['nim'] ?? $row['student_number'] ?? ($module === 'krs' ? ($row[0] ?? null) : null) ?? $parsedNim);
            $name = $module === 'course' ? null : self::text($row['nama'] ?? $row['nama_mahasiswa'] ?? $row['nama_siswa'] ?? $row['name'] ?? ($module === 'krs' ? ($row[1] ?? null) : null));
            $message = preg_replace('/^Baris #\d+:\s*/u', '', $error);
            if (preg_match('/^Baris NIM [^:]+: Error/u', $message)) {
                $message = 'Penyimpanan profil akademik gagal; periksa log aplikasi.';
            }
            $details[] = ['nim' => $nim, 'name' => $name, 'record' => $index === null ? null : $index + 1,
                'path' => $index === null ? null : ($locations[$index]['path'] ?? '$['.$index.']'),
                'line' => $index === null ? null : ($locations[$index]['line'] ?? null), 'message' => $message];
        }
        return $details;
    }

    /** Surface real source failures even when their failed fields were omitted. */
    public static function withSourceFailures(array $errors, array $rows): array
    {
        $normalizer = new AcademicProfileJsonNormalizer;
        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;
            foreach ($normalizer->failures($row['error'] ?? null) as $menu) {
                $message = $menu === '*' ? 'Pengambilan data sumber gagal.' : 'Pengambilan data menu '.$menu.' gagal.';
                $note = "Baris #{$index}: {$message}";
                if (!in_array($note, $errors, true)) $errors[] = $note;
            }
        }
        return $errors;
    }

    /** Keep the old TEXT field readable; complete notes live in issue_details. */
    public static function summary(array $errors): ?string
    {
        if ($errors === []) return null;
        $summary = implode(' | ', $errors);
        return strlen($summary) <= 60000 ? $summary
            : mb_strcut($summary, 0, 59000, 'UTF-8').' … Seluruh catatan tersedia pada detail sinkronisasi.';
    }

    public static function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
