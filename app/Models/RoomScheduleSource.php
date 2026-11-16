<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomScheduleSource extends Model
{
    protected $guarded = [];

    protected $casts = ['is_complete' => 'boolean', 'metadata' => 'array', 'version' => 'integer'];

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
