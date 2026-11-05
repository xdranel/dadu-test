<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'module', 'status', 'total_records',
        'processed_records', 'skipped_records',
        'error_message', 'triggered_by',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getDurasiAttribute(): string
    {
        if (!$this->started_at || !$this->completed_at) return '-';
        return $this->started_at->diffForHumans($this->completed_at, true);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'completed'  => 'badge-success',
            'processing' => 'badge-info',
            'pending'    => 'badge-warning',
            'failed'     => 'badge-danger',
            default      => 'badge-warning',
        };
    }
}