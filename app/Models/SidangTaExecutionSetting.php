<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaExecutionSetting extends Model
{
    protected $guarded = [];

    protected $attributes = ['allowed_weekdays' => '[1,2,3,4,5]', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        'break_start_time' => null, 'break_end_time' => null, 'duration_minutes' => 90, 'is_active' => true, 'lock_version' => 1];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'allowed_weekdays' => 'array', 'duration_minutes' => 'integer', 'lock_version' => 'integer', 'is_active' => 'boolean'];

    public function period()
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
