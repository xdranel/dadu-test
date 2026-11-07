<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicketHistory extends Model
{
    protected $table = 'queue_ticket_histories';

    // id is not auto-incrementing (copied from queue_tickets)
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'nim',
        'name',
        'study_program',
        'service_category',
        'notes',
        'queue_date',
        'status',
        'device_token',
        'counter_id',
        'called_by',
        'called_at',
        'finished_at',
        'skipped_at',
        'skipped_by',
    ];

    protected $casts = [
        'queue_date' => 'string',
        'called_at' => 'datetime',
        'finished_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(QueueCounter::class, 'counter_id');
    }

    public function calledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by');
    }

    public function skippedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'skipped_by');
    }
}
