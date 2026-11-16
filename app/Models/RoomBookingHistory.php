<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBookingHistory extends Model
{
    protected $guarded = [];

    protected $casts = ['before_data' => 'array', 'after_data' => 'array'];

    public function request()
    {
        return $this->belongsTo(RoomBookingRequest::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
