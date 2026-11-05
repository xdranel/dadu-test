<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
    protected $table = 'student_courses';

    protected $fillable = [
        'student_number',
        'course_code',
        'course_name',
        'course_class',
        'lecturer_code',
        'lecturer_name',
        'faculty',
        'study_program',
        'academic_year',
        'semester',
        'academic_period_id',
        'credits',
        'quota',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
