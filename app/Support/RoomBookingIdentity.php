<?php

namespace App\Support;

use App\Models\Lecturer;
use Illuminate\Support\Collection;

final class RoomBookingIdentity
{
    private Collection $byEmail;

    private Collection $byNip;

    private Collection $byCode;

    public function __construct()
    {
        $lecturers = Lecturer::get(['email_domain', 'nip_ypt', 'lecturer_code', 'study_program']);
        $this->byEmail = $lecturers->groupBy(fn (Lecturer $lecturer) => $this->key($lecturer->email_domain));
        $this->byNip = $lecturers->groupBy(fn (Lecturer $lecturer) => $this->key($lecturer->nip_ypt));
        $this->byCode = $lecturers->groupBy(fn (Lecturer $lecturer) => $this->key($lecturer->lecturer_code));
    }

    public function lecturer(?string $email, ?string $employeeId): ?Lecturer
    {
        return $this->uniqueLecturer($this->byEmail, $email) ?? $this->uniqueLecturer($this->byNip, $employeeId);
    }

    public function programForCode(?string $code): ?string
    {
        return $this->uniqueLecturer($this->byCode, $code)?->study_program;
    }

    private function uniqueLecturer(Collection $index, ?string $value): ?Lecturer
    {
        $key = $this->key($value);
        $matches = $key !== '' && $key !== '-' ? $index->get($key) : null;

        return $matches?->count() === 1 ? $matches->first() : null;
    }

    private function key(?string $value): string
    {
        return mb_strtolower(trim($value ?? ''));
    }
}
