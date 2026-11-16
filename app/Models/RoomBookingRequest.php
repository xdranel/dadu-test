<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBookingRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'cancelled_at' => 'datetime'];

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function occurrences()
    {
        return $this->hasMany(RoomBookingOccurrence::class, 'request_id')->orderBy('usage_date');
    }

    public function histories()
    {
        return $this->hasMany(RoomBookingHistory::class, 'request_id')->latest();
    }
}
