<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncSetting extends Model
{
    protected $table = 'sync_settings';

    protected $fillable = [
        'module', 'label', 'endpoint_url',
        'is_active', 'icon', 'urutan',
    ];

    protected $casts = ['is_active' => 'boolean'];
}