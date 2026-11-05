<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourseIssue extends Model
{
    protected $table = 'student_course_issues';

    protected $fillable = [
        'student_number',
        'retaking_courses',
        'failed_courses',
        'credits_passed',
        'credits_remaining',
    ];

    protected $casts = [
        'retaking_courses'  => 'array',
        'failed_courses'    => 'array',
        'credits_passed'    => 'integer',
        'credits_remaining' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
