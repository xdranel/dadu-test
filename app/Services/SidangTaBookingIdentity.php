<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\SidangTaRegistration;
use App\Models\StudentFinalProject;

class SidangTaBookingIdentity
{
    public function forRegistration(SidangTaRegistration $registration): array
    {
        $project = StudentFinalProject::where('student_number', $registration->student_number)->first();
        $identity = ['registration_id' => $registration->id, 'student_number' => $registration->student_number, 'student_name' => $registration->student_name];
        foreach (['advisor_1', 'advisor_2', 'examiner_1', 'examiner_2'] as $key) {
            $name = $project?->$key;
            if (! $name || trim($name) === '-') {
                $name = $registration->academic_snapshot[$key] ?? null;
            }
            $code = $name && preg_match('/^[A-Z0-9]{2,6}$/D', trim($name)) ? trim($name) : null;
            if ($name && trim($name) !== '-') {
                $lecturers = Lecturer::where('lecturer_code', $name)->orWhere('name', $name)->limit(2)->get();
                if ($lecturers->count() === 1) {
                    $code = $lecturers->first()->lecturer_code;
                    $name = $lecturers->first()->full_name;
                }
            }
            $identity[$key] = ['name' => $name && trim($name) !== '-' ? $name : 'Belum tersedia', 'code' => $code ?: 'Belum tersedia'];
        }

return $identity;
    }
}
