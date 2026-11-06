<?php

namespace App\Support;

use InvalidArgumentException;

class OfficialKrsJsonNormalizer
{
    public static function supports(array $payload): bool
    {
        if (is_array($payload['selected_menus'] ?? null)
            && in_array('krs_mk', $payload['selected_menus'], true)) return true;
        if (array_key_exists('krs_mata_kuliah', $payload)) return true;
        $rows = $payload['data'] ?? $payload;
        if (!is_array($rows)) return false;
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists('krs_mata_kuliah', $row)) return true;
        }
        return false;
    }

    public function rows(array $payload): array
    {
        $wrapped = array_key_exists('data', $payload);
        $rows = $wrapped ? $payload['data']
            : (array_key_exists('krs_mata_kuliah', $payload) ? [$payload] : $payload);
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('Data KRS harus berupa daftar mahasiswa.');
        }
        if ($wrapped && array_key_exists('selected_menus', $payload)) {
            $menus = $payload['selected_menus'];
            if (!is_array($menus) || !array_is_list($menus)
                || array_filter($menus, fn ($v) => !is_string($v)) !== []
                || !in_array('krs_mk', $menus, true)) {
                throw new InvalidArgumentException('Menu ekspor harus memuat krs_mk.');
            }
        }
        if ($wrapped && array_key_exists('total', $payload)
            && (!is_int($payload['total']) || $payload['total'] !== count($rows))) {
            throw new InvalidArgumentException('Total mahasiswa tidak cocok dengan isi ekspor.');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['nim'] ?? null)) continue;
            $nim = trim($row['nim']);
            if (isset($seen[$nim])) throw new InvalidArgumentException('NIM berulang dalam satu ekspor.');
            $seen[$nim] = true;
        }
        return $rows;
    }

    public function normalize(array $row): array
    {
        if (array_key_exists('error', $row) && $row['error'] !== null && $row['error'] !== '') {
            throw new InvalidArgumentException('Pengambilan KRS sumber gagal; data lama dipertahankan.');
        }
        $nim = $this->text($row['nim'] ?? null, 15);
        if (preg_match('/^\d{1,15}$/D', $nim) !== 1) {
            throw new InvalidArgumentException('NIM KRS tidak valid.');
        }
        $name = $this->text($row['nama'] ?? null, 255);
        $courses = $row['krs_mata_kuliah'] ?? null;
        $count = $row['jumlah_mk_krs'] ?? null;
        if (!is_array($courses) || !array_is_list($courses)
            || !is_int($count) || $count < 0 || $count !== count($courses)) {
            throw new InvalidArgumentException('Daftar atau jumlah mata kuliah KRS tidak valid.');
        }
        $result = $seen = [];
        foreach ($courses as $course) {
            if (!is_array($course)) throw new InvalidArgumentException('Item KRS harus berupa objek.');
            foreach (['kode_mk', 'nama_mk', 'kelas', 'kode_dosen', 'tahun_ajaran', 'semester', 'program_studi'] as $key) {
                if (!array_key_exists($key, $course)) throw new InvalidArgumentException('Field KRS sumber belum lengkap.');
            }
            $rawYear = $this->text($course['tahun_ajaran'], 4);
            if (preg_match('/^(\d{2})(\d{2})$/D', $rawYear, $parts) !== 1
                || (((int) $parts[1] + 1) % 100) !== (int) $parts[2]) {
                throw new InvalidArgumentException('Tahun ajaran KRS harus berupa dua tahun berurutan, misalnya 2627.');
            }
            if (!in_array($course['semester'], ['1', '2'], true)) {
                throw new InvalidArgumentException('Term KRS harus string 1 atau 2, bukan semester studi.');
            }
            $start = 2000 + (int) $parts[1];
            $item = ['course_code' => $this->text($course['kode_mk'], 20),
                'course_name' => $this->text($course['nama_mk'], 255),
                'course_class' => $this->text($course['kelas'], 255),
                'lecturer_code' => $this->text($course['kode_dosen'], 20, true),
                'study_program' => $this->text($course['program_studi'], 255),
                'academic_year' => $start.'/'.($start + 1), 'term' => (int) $course['semester']];
            $key = json_encode([$item['academic_year'], $item['term'], $item['course_code']], JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) throw new InvalidArgumentException('Mata kuliah berulang pada periode yang sama.');
            $seen[$key] = true;
            $result[] = $item;
        }
        return ['student_number' => $nim, 'name' => $name, 'courses' => $result];
    }

    private function text(mixed $value, int $max, bool $nullable = false): ?string
    {
        if ($nullable && $value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Field teks KRS tidak valid.');
        $value = trim($value);
        if ($nullable && $value === '') return null;
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Field teks KRS kosong atau melebihi panjang kolom.');
        }
        return $value;
    }
}
