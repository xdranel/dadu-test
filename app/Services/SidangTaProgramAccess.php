<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\SidangTaRegistration;
use App\Models\User;

/** Lecturer identity and program scope are resolved afresh for each request. */
class SidangTaProgramAccess
{
    public const PERMISSION = 'sidang_ta_registration_view_program';

    public function programFor(User $user): string
    {
        abort_unless($user->can(self::PERMISSION), 403);
        $email = mb_strtolower(trim((string) $user->email));
        abort_if($email === '', 403, 'Email akun belum terhubung dengan Data Dosen.');
        // The database's default accent-insensitive collation must not widen identity matching.
        $query = Lecturer::query();
        $column = $query->getConnection()->getDriverName() === 'mysql'
            ? 'CAST(LOWER(TRIM(email_domain)) AS BINARY)' : 'LOWER(TRIM(email_domain))';
        $matches = $query->whereRaw($column.' = ?', [$email])->limit(2)->get();
        abort_unless($matches->count() === 1, 403,
            'Akses baca memerlukan satu Data Dosen dengan Email Domain yang sesuai akun. Hubungi administrator.');
        $program = trim((string) $matches->first()->study_program);
        abort_if($program === '' || $program === '-', 403, 'Program studi pada Data Dosen belum tersedia.');
        return $program;
    }

    /** Use only complete program labels, never a substring or organizational unit. */
    public function matchingPrograms(string $program): array
    {
        $query = SidangTaRegistration::query();
        $column = $query->getConnection()->getDriverName() === 'mysql'
            ? 'CAST(study_program AS BINARY)' : 'study_program';
        return $query->selectRaw($column.' as study_program')->whereNotNull('study_program')->distinct()
            ->pluck('study_program')->filter(fn ($value) => $this->sameProgram($program, $value))->values()->all();
    }

    public function authorize(User $user, SidangTaRegistration $registration): void
    {
        if ($user->can('sidang_ta_registration_review')) return;
        abort_unless($this->sameProgram($this->programFor($user), $registration->study_program), 403);
    }

    private function sameProgram(string $program, ?string $other): bool
    {
        $key = $this->programKey($program);
        return $key !== '' && $key === $this->programKey((string) $other);
    }

    private function programKey(string $program): string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $program)));
        $key = preg_replace('/^s1\s+/u', '', $key);
        $key = preg_replace('/\s*-\s*kampus surabaya$/u', '', $key);
        // Data Dosen uses this explicit alternative spelling.
        return $key === 'digital bisnis' ? 'bisnis digital' : $key;
    }
}
