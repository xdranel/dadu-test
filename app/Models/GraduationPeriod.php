<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class GraduationPeriod extends Model
{
    protected $fillable = [
        'name',
        'open_at',
        'close_at',
        'is_active',
        'description',
    ];

    protected $casts = [
        'open_at'   => 'datetime',
        'close_at'  => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isOpen(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = now();
        return $now->gte($this->open_at) && $now->lte($this->close_at);
    }

    public function isUpcoming(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        return now()->lt($this->open_at);
    }

    public function isClosed(): bool
    {
        if (!$this->is_active) {
            return true;
        }
        return now()->gt($this->close_at);
    }

    public static function getActivePeriod(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
