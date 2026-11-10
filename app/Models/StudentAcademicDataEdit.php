<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicDataEdit extends Model
{
    protected $fillable = ['student_number', 'user_id', 'before_data', 'after_data', 'note'];
    protected $casts = ['before_data' => 'array', 'after_data' => 'array'];
}
