<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistrationLog extends Model
{
    protected $fillable = [
        'sidang_ta_registration_id', 'action', 'from_status', 'to_status', 'notes', 'performed_by', 'performed_at',
    ];

    protected $casts = ['performed_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }
}
