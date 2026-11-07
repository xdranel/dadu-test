<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicketEvent extends Model
{
    use HasFactory;

    protected $table = 'queue_ticket_events';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'queue_date',
        'nim',
        'number',
        'event',
        'counter_id',
        'user_id',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'queue_date' => 'string',
        'number' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke master loket
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(QueueCounter::class, 'counter_id');
    }

    /**
     * Relasi ke petugas/pengguna
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper praktis untuk mencatat event tiket di dalam transaksi.
     */
    public static function record(
        string $event,
        $ticket,
        ?int $counterId = null,
        ?int $userId = null,
        ?array $meta = null
    ): self {
        $queueDate = $ticket->queue_date;
        if (!$queueDate) {
            $queueDate = $ticket->created_at ? $ticket->created_at->timezone('Asia/Jakarta')->toDateString() : now('Asia/Jakarta')->toDateString();
        }

        return self::create([
            'ticket_id' => $ticket->id ?? null,
            'queue_date' => $queueDate,
            'nim' => $ticket->nim ?? null,
            'number' => (int) ($ticket->code ?? 0),
            'event' => $event,
            'counter_id' => $counterId ?? ($ticket->counter_id ?? null),
            'user_id' => $userId ?? ($ticket->called_by ?? ($ticket->skipped_by ?? null)),
            'meta' => $meta,
            'created_at' => now('Asia/Jakarta'),
        ]);
    }
}
