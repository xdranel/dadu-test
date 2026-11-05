<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
    protected $table = 'student_attendances';

    protected $fillable = [
        'student_number',
        'course_code',
        'course_name',
        'class',
        'attended_count',
        'meetings_count',
        'academic_year',
        'semester',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }

    public function getPercentageAttribute(): float
    {
        return $this->meetings_count > 0
            ? round(($this->attended_count / $this->meetings_count) * 100, 2)
            : 0;
    }

    public function getBadgeColorAttribute(): string
    {
        return match(true) {
            $this->percentage >= 80 => 'badge-success',
            $this->percentage >= 60 => 'badge-warning',
            default                 => 'badge-danger',
        };
    }
}