<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaExecutionLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['data' => 'array', 'performed_at' => 'datetime'];

    public function schedule()
    {
        return $this->belongsTo(SidangTaSchedule::class, 'sidang_ta_schedule_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
