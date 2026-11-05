<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFinalProject extends Model
{
    protected $table = 'student_final_projects';

    protected $fillable = [
        'student_number',
        'thesis_title_id',
        'thesis_title_en',
        'advisor_1',
        'advisor_2',
        'examiner_1',
        'examiner_2',
        'examiner_3',
        'exam_date',
        'exam_time',
        'exam_location',
    ];

    protected $casts = [
        'exam_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
