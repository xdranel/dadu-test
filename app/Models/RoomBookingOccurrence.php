<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBookingOccurrence extends Model
{
    protected $guarded = [];

    protected $casts = ['usage_date' => 'date'];

    public function request()
    {
        return $this->belongsTo(RoomBookingRequest::class, 'request_id');
    }
}
