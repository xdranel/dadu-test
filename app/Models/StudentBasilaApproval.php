<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBasilaApproval extends Model
{
    protected $table = 'student_basila_approvals';

    protected $fillable = [
        'student_number',
        'photo_approval',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'photo_approval' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
