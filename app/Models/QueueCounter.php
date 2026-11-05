<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QueueCounter extends Model
{
    use HasFactory;

    protected $table = 'queue_counters';

    protected $fillable = [
        'name',
        'active_user_id',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function activeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'active_user_id');
    }

    public function queueState(): HasOne
    {
        return $this->hasOne(QueueState::class, 'counter_id');
    }

    public function calledTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class, 'counter_id');
    }
}
