<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistrationDocument extends Model
{
    protected $fillable = [
        'sidang_ta_registration_id', 'document_type', 'file_name', 'file_path',
        'file_size', 'file_type', 'status', 'notes',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }
}
