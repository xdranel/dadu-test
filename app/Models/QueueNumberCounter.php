<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueNumberCounter extends Model
{
    protected $table = 'queue_number_counters';

    protected $fillable = [
        'queue_date',
        'last_number',
    ];

    protected $casts = [
        'queue_date' => 'string',
        'last_number' => 'integer',
    ];
}
