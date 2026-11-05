<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SidangTaStudentContext
{
    public function studentFor(User $user): Student
    {
        if (filled($user->email)) {
            $student = Student::where('email', $user->email)->first();
            if ($student) {
                return $student;
            }
        }

        $numbers = array_values(array_filter([
            $user->username, $user->employee_id,
        ], fn($value) => filled($value)));
        $matches = Student::whereIn('student_number', $numbers)->limit(2)->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'student' => 'Akun belum terhubung dengan satu data mahasiswa yang valid'
            ]);
        }

        return $matches->first();
    }
}
