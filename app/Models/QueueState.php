<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueState extends Model
{
    use HasFactory;

    protected $table = 'queue_states';

    protected $fillable = [
        'counter_id',
        'current_id',
        'last_id',
        'call_seq',
    ];

    protected $casts = [
        'call_seq' => 'integer',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(QueueCounter::class, 'counter_id');
    }

    public function currentTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'current_id');
    }

    public function lastTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'last_id');
    }
}
