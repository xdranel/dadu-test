<?php

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class WeeklyRoomScheduleNormalizer
{
    public static function supports(array $payload): bool
    {
        if (in_array('jadwal_perkuliahan', (array) ($payload['selected_menus'] ?? []), true)) {
            return true;
        }
        foreach ((array) ($payload['data'] ?? []) as $row) {
            if (is_array($row) && (isset($row['periode']) || isset($row['jenis_jadwal']))) {
                return true;
            }
        }

        return false;
    }

    public static function period(string $tag): array
    {
        if (! preg_match('/^(\d{2})(\d{2})\/([12])$/D', trim($tag), $m) || ((int) $m[1] + 1) % 100 !== (int) $m[2]) {
            throw new InvalidArgumentException('Periode sumber harus seperti 2627/1 atau 2627/2.');
        }
        $year = 2000 + (int) $m[1];

        return ['year' => $year.'/'.($year + 1), 'semester' => (int) $m[3]];
    }

    public static function roomKey(string $label): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($label)));
    }

    public function normalize(array $payload, array $periodContext): array
    {
        $rows = $payload['data'] ?? null;
        $filter = $payload['filter_jadwal_perkuliahan'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === [] || ! is_int($payload['total'] ?? null) || $payload['total'] !== count($rows)) {
            throw new InvalidArgumentException('Daftar/total jadwal tidak lengkap. Jadwal lama dipertahankan.');
        }
        if (! is_string($payload['source'] ?? null) || mb_strtolower(trim($payload['source'])) !== 'igracias' || ! is_array($payload['selected_menus'] ?? null) || ! in_array('jadwal_perkuliahan', $payload['selected_menus'], true)) {
            throw new InvalidArgumentException('Sumber harus ekspor jadwal_perkuliahan iGracias.');
        }
        if (! is_array($filter) || ! is_string($filter['schoolYear'] ?? null)) {
            throw new InvalidArgumentException('Filter periode sumber belum tersedia.');
        }
        foreach (['studyProgramId', 'roomId', 'isConflict'] as $key) {
            if (! array_key_exists($key, $filter) || ($filter[$key] !== null && $filter[$key] !== '')) {
                throw new InvalidArgumentException('Gunakan snapshot semester lengkap tanpa filter prodi/ruang/bentrok.');
            }
        }
        foreach ($filter as $key => $value) {
            if ($key !== 'schoolYear' && $value !== null && $value !== '') {
                throw new InvalidArgumentException('Ekspor berfilter tidak mengganti snapshot lengkap.');
            }
        }
        $period = self::period($filter['schoolYear']);
        if ($period['year'] !== $periodContext['year'] || $period['semester'] !== (int) $periodContext['semester']) {
            throw new InvalidArgumentException('Periode sumber tidak cocok dengan periode terdaftar.');
        }
        $days = ['SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, 'JUMAT' => 5, 'SABTU' => 6, 'MINGGU' => 7];
        $patterns = [];
        $sourceDates = [];
        foreach ($rows as $i => $row) {
            try {
                if (! is_array($row)) {
                    throw new InvalidArgumentException('Baris harus berupa objek.');
                }
                foreach (['periode', 'hari', 'tanggal', 'ruangan', 'jam_mulai', 'jam_selesai', 'jenis_jadwal'] as $key) {
                    if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                        throw new InvalidArgumentException('Field '.$key.' belum lengkap.');
                    }
                }
                if (trim($row['periode']) !== trim($filter['schoolYear'])) {
                    throw new InvalidArgumentException('Sumber periode saling bertentangan.');
                }
                if (mb_strtoupper(trim($row['jenis_jadwal'])) !== 'TETAP') {
                    throw new InvalidArgumentException('Jenis jadwal tidak didukung sebagai pola mingguan.');
                }
                $date = DateTimeImmutable::createFromFormat('!d-m-Y', $row['tanggal']);
                if (! $date || $date->format('d-m-Y') !== $row['tanggal']) {
                    throw new InvalidArgumentException('Tanggal tidak valid (DD-MM-YYYY).');
                }
                $day = $days[mb_strtoupper(trim($row['hari']))] ?? null;
                if ($day !== (int) $date->format('N')) {
                    throw new InvalidArgumentException('Hari berbeda dengan tanggal referensi.');
                }
                $ymd = $date->format('Y-m-d');
                if ($ymd < $periodContext['start_date'] || $ymd > $periodContext['end_date']) {
                    throw new InvalidArgumentException('Tanggal referensi di luar batas semester terdaftar.');
                }
                $start = RoomTimeWindows::minutes($row['jam_mulai']);
                $end = RoomTimeWindows::minutes($row['jam_selesai'], true);
                $startTime = strlen($row['jam_mulai']) === 5 ? $row['jam_mulai'].':00' : $row['jam_mulai'];
                $endTime = strlen($row['jam_selesai']) === 5 ? $row['jam_selesai'].':00' : $row['jam_selesai'];
                if ($startTime >= $endTime) {
                    throw new InvalidArgumentException('Jam selesai harus setelah mulai.');
                }
                $label = trim($row['ruangan']);
                if (mb_strlen($label) > 255 || self::roomKey($label) === '') {
                    throw new InvalidArgumentException('Identitas ruang tidak valid.');
                }
                $lecturer = $row['kode_dosen'] ?? null;
                $description = $row['nama_mk'] ?? null;
                if (($lecturer !== null && ! is_string($lecturer)) || ($description !== null && ! is_string($description))) {
                    throw new InvalidArgumentException('Kode dosen/keterangan tidak valid.');
                }
                if (mb_strlen($lecturer ?? '') > 50 || mb_strlen($description ?? '') > 255) {
                    throw new InvalidArgumentException('Kode dosen/keterangan terlalu panjang.');
                }
                $pattern = ['source_room_label' => $label, 'weekday' => $day, 'start_time' => $startTime, 'end_time' => $endTime, 'lecturer_code' => $lecturer, 'description' => $description];
                $key = hash('sha256', json_encode([self::roomKey($label), $day, $start, $end, $lecturer, $description], JSON_THROW_ON_ERROR));
                if (! isset($patterns[$key])) {
                    $patterns[$key] = $pattern + ['source_identity' => $key, 'reference_date' => $ymd];
                }
                $sourceDates[$ymd] = true;
            } catch (\Throwable $e) {
                throw new InvalidArgumentException('Baris '.($i + 1).': '.$e->getMessage(), 0, $e);
            }
        }
        ksort($patterns);
        ksort($sourceDates);

        return ['patterns' => array_values($patterns), 'period_id' => (int) $periodContext['id'], 'source_week' => array_keys($sourceDates), 'scope' => 'semester', 'fingerprint' => hash('sha256', json_encode(array_keys($patterns),JSON_THROW_ON_ERROR))];
    }
}
