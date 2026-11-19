<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaSchedule extends Model
{
    protected $guarded = [];

    protected $attributes = ['mode' => 'automatic', 'state' => 'unassigned', 'was_confirmed' => false,
        'lock_version' => 1, 'planning_source' => 'system', 'result_status' => 'pending'];

    protected $casts = ['was_confirmed' => 'boolean', 'lock_version' => 'integer',
        'participant_data' => 'array', 'details_snapshot' => 'array', 'preferred_date' => 'date', 'result_at' => 'datetime'];

    public function executionLogs()
    {
        return $this->hasMany(SidangTaExecutionLog::class)->orderByDesc('performed_at')->orderByDesc('id');
    }

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }

    public function booking()
    {
        return $this->belongsTo(RoomBookingRequest::class, 'room_booking_request_id');
    }
}
