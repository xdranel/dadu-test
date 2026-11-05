<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFinalProjectGrade extends Model
{
    protected $table = 'student_final_project_grades';

    protected $fillable = [
        'student_number',
        'grade',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
