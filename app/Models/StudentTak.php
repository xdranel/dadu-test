<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTak extends Model
{
    protected $table = 'student_taks';

    protected $fillable = [
        'student_number',
        'total_points',
        'test_date',
        'expiry_date',
    ];

    protected $casts = [
        'total_points' => 'decimal:2',
        'test_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
