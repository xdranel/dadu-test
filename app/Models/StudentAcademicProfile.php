<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicProfile extends Model
{
    protected $fillable = ['student_number', 'groups', 'bpp_periods'];

    protected $attributes = ['groups' => '{}', 'bpp_periods' => '{}'];

    protected $casts = ['groups' => 'array', 'bpp_periods' => 'array'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
