<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    protected $table = 'student_grades';

    protected $fillable = [
        'student_number',
        'course_code',
        'course_name',
        'credits',
        'type',
        'grade',
        'level',
        'academic_year',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}