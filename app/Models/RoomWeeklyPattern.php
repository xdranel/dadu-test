<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomWeeklyPattern extends Model
{
    protected $guarded = [];

    protected $casts = ['weekday' => 'integer', 'reference_date' => 'date'];

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
