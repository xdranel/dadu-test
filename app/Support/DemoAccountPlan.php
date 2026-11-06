<?php

namespace App\Support;

use InvalidArgumentException;

/** Account identities only; academic eligibility remains an explicit JSON import. */
class DemoAccountPlan
{
    public static function local(): array
    {
        return [
            ['username' => 'superadmin', 'name' => 'Super Admin Test', 'email' => 'superadmin@example.test',
                'password' => 'password', 'roles' => ['Akademik'], 'student' => null],
            ['username' => '1202200001', 'name' => 'Krisna Mahasiswa', 'email' => 'krisna@mahasiswa.test',
                'password' => 'password', 'roles' => ['mahasiswa', 'Petugas'],
                'student' => ['student_number' => '1202200001', 'name' => 'Krisna Mahasiswa',
                    'email' => 'krisna@mahasiswa.test', 'academic_year' => 2022,
                    'active_semester' => 8, 'study_program' => 'Informatika', 'status' => 'Aktif']],
        ];
    }

    public static function staging(array $rows, string $fixedPassword): array
    {
        if (strlen($fixedPassword) < 12) {
            throw new InvalidArgumentException('DADU_TEST_PASSWORD untuk dua akun tetap harus minimal 12 karakter.');
        }
        $base = self::local();
        foreach ($base as &$account) $account['password'] = $fixedPassword;
        unset($account);
        return array_merge($base, self::blackbox($rows));
    }

    public static function blackbox(array $rows): array
    {
        if (!array_is_list($rows) || count($rows) !== 11) {
            throw new InvalidArgumentException('Roster blackbox harus berisi tepat 11 mahasiswa.');
        }
        $seenNims = ['1202200001' => true];
        $seenEmails = ['superadmin@example.test' => true, 'krisna@mahasiswa.test' => true];
        $accounts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Baris roster blackbox tidak valid.');
            foreach (['nim', 'nama', 'program_studi'] as $key) {
                if (!is_string($row[$key] ?? null) || trim($row[$key]) === '' || mb_strlen(trim($row[$key])) > 255) {
                    throw new InvalidArgumentException('NIM, nama, dan program studi roster harus lengkap.');
                }
            }
            $nim = trim($row['nim']);
            if (preg_match('/^(?:\d{10}|\d{12})$/D', $nim) !== 1 || isset($seenNims[$nim])) {
                throw new InvalidArgumentException('NIM roster tidak valid, berulang, atau memakai akun tetap.');
            }
            $semester = $row['semester'] ?? null;
            if ((!is_string($semester) && !is_int($semester))
                || filter_var($semester, FILTER_VALIDATE_INT) === false || (int) $semester < 1 || (int) $semester > 127) {
                throw new InvalidArgumentException('Semester studi roster harus berupa angka 1 sampai 127.');
            }
            $name = trim($row['nama']);
            $email = self::emailForName($name);
            if (isset($seenEmails[$email])) throw new InvalidArgumentException('Email roster berulang atau memakai akun tetap.');
            $seenNims[$nim] = $seenEmails[$email] = true;
            $student = ['student_number' => $nim, 'name' => $name, 'email' => $email,
                'academic_year' => 2000 + (int) substr($nim, strlen($nim) === 12 ? 5 : 4, 2),
                'active_semester' => (int) $semester, 'study_program' => trim($row['program_studi']), 'status' => 'Aktif'];
            $accounts[] = ['username' => $nim, 'name' => mb_strtolower($name), 'email' => $email,
                'password' => 'password', 'roles' => ['mahasiswa'], 'student' => $student];
        }
        return $accounts;
    }

    public static function emailForName(string $name): string
    {
        $local = preg_replace('/\s+/u', '', mb_strtolower(trim($name)));
        $email = $local.'@mahasiswa.test';
        if ($local === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Nama tidak dapat dipakai sebagai email mahasiswa yang valid.');
        }
        return $email;
    }
}
