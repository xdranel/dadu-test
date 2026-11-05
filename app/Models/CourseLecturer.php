<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLecturer extends Model
{
    protected $table = 'course_lecturers';

    protected $fillable = [
        'course_code',
        'lecturer_code',
        'role',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_code', 'course_code');
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_code', 'lecturer_code');
    }
}
