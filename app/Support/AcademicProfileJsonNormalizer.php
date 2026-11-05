<?php

namespace App\Support;

use InvalidArgumentException;

/** Reads one imported row. No database, HTTP, or eligibility decisions. */
class AcademicProfileJsonNormalizer
{
    private const GROUPS = [
        'skpi' => ['skpi', ['status_skpi']],
        'sidang_approval' => ['maju_sidang', ['persetujuan_maju_sidang']],
        'language' => ['bahasa', ['nilai_bahasa', 'jenis_tes_bahasa', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa']],
        'tak' => ['tak', ['total_tak']],
        'bpp' => ['keuangan', ['status_keuangan']],
    ];

    /**
     * Missing groups are intentionally absent from updates: preserve old data.
     * financePeriod comes from confirmed import context, never the current date.
     */
    public function normalize(array $row, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        if ($selectedMenus !== null && (!array_is_list($selectedMenus)
                || array_filter($selectedMenus, fn ($menu) => !is_string($menu)) !== [])) {
            throw new InvalidArgumentException('selected_menus harus berupa daftar nama menu.');
        }

        $result = ['updates' => [], 'skipped' => []];
        $failures = $this->failures($row['error'] ?? null);
        foreach (self::GROUPS as $group => [$menu, $fields]) {
            if ($selectedMenus !== null && !in_array($menu, $selectedMenus, true)) continue;
            $present = array_filter($fields, fn ($key) => array_key_exists($key, $row));
            if ($present === []) continue;
            if (in_array('*', $failures, true) || in_array($menu, $failures, true)) {
                $result['skipped'][$group] = 'Pengambilan data menu '.$menu.' gagal.';
                continue;
            }

            try {
                $result['updates'][$group] = match ($group) {
                    'skpi' => $this->approval($row['status_skpi'], 'Disetujui'),
                    'sidang_approval' => $this->approval($row['persetujuan_maju_sidang'], 'APPROVED'),
                    'language' => $this->language($row),
                    'tak' => ['score' => $this->number($row['total_tak'])],
                    'bpp' => $this->bpp($row, $financePeriod),
                };
            } catch (InvalidArgumentException $exception) {
                $result['skipped'][$group] = $exception->getMessage();
            }
        }
        return $result;
    }

    private function approval(mixed $value, string $accepted): array
    {
        $status = $this->text($value);
        return ['status' => $status, 'approved' => $status === $accepted];
    }

    private function language(array $row): array
    {
        foreach (self::GROUPS['language'][1] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new InvalidArgumentException('Ringkasan bahasa tidak lengkap; pertahankan data sebelumnya.');
            }
        }
        $history = $row['riwayat_nilai_bahasa'];
        if (!is_array($history) || !array_is_list($history)) {
            throw new InvalidArgumentException('Riwayat bahasa harus berupa daftar.');
        }
        foreach ($history as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('Setiap riwayat bahasa harus berupa objek.');
            }
        }
        return [
            'score' => $this->number($row['nilai_bahasa']),
            'type' => $this->text($row['jenis_tes_bahasa']),
            'attempts' => $this->count($row['jumlah_tes_bahasa']),
            'history' => $history,
        ];
    }

    private function bpp(array $row, ?string $financePeriod): array
    {
        $status = $this->text($row['status_keuangan']);
        $raw = $this->text($row['status_keuangan_asli'] ?? null);
        $semester = $row['semester'] ?? null;
        $semester = $this->text(is_int($semester) ? (string) $semester : $semester);
        $rowPeriod = $semester !== null && preg_match('/^\d{4}\/[12]$/D', $semester) === 1 ? $semester : null;
        if ($financePeriod !== null) {
            $financePeriod = trim($financePeriod);
            if (preg_match('/^\d{4}\/[12]$/D', $financePeriod) !== 1) {
                throw new InvalidArgumentException('Periode BPP harus berbentuk 2627/1 atau 2627/2.');
            }
            if ($rowPeriod !== null && $rowPeriod !== $financePeriod) {
                throw new InvalidArgumentException('Periode BPP pada baris berbeda dengan konteks impor.');
            }
        }
        return [
            'status' => $status === 'PERLU VERIFIKASI' && $raw === 'KREDIT' ? 'KREDIT' : $status,
            'source_status' => $status,
            'raw_status' => $raw,
            'source_semester' => $semester,
            'period' => $financePeriod ?? $rowPeriod,
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Status atau jenis tes harus berupa teks atau null.');
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || is_string($value) && trim($value) === '') return null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException('Nilai numerik harus berupa angka nonnegatif atau null.');
        }
        return (float) $value;
    }

    private function count(mixed $value): ?int
    {
        if ($value === null || is_string($value) && trim($value) === '') return null;
        if ((!is_int($value) && !is_string($value))
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException('Jumlah tes harus berupa bilangan bulat nonnegatif atau null.');
        }
        return (int) $value;
    }

    public function failures(mixed $error): array
    {
        if ($error === null || $error === '') return [];
        if (!is_string($error)) return ['*'];
        $error = trim($error);
        if ($error === '') return [];
        preg_match_all('/(?:^|[;\r\n])\s*(akademik|bahasa|tak|keuangan|mata_kuliah|jadwal_sidang|skpi|maju_sidang|basila_foto)\s*:/i', $error, $matches);
        return $matches[1] === [] ? ['*'] : array_unique(array_map('strtolower', $matches[1]));
    }
}
