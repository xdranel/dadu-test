<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSidangTingkat extends Model
{
    protected $table = 'student_sidang_tingkats';

    protected $fillable = [
        'student_number',
        'tingkat_1',
        'tingkat_2',
        'tingkat_3',
        'tingkat_4',
    ];

    protected $casts = [
        'tingkat_1' => 'boolean',
        'tingkat_2' => 'boolean',
        'tingkat_3' => 'boolean',
        'tingkat_4' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
