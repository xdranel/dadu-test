<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaSchedule extends Model
{
    protected $guarded = [];

    protected $attributes = ['mode' => 'automatic', 'state' => 'unassigned', 'was_confirmed' => false, 'lock_version' => 1];

    protected $casts = ['was_confirmed' => 'boolean', 'lock_version' => 'integer'];

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }

    public function booking()
    {
        return $this->belongsTo(RoomBookingRequest::class, 'room_booking_request_id');
    }
}
