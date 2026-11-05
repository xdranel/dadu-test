<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SidangTaPeriod extends Model
{
    protected $fillable = ['name', 'open_at', 'close_at', 'is_active', 'description'];

    protected $casts = [
        'open_at' => 'datetime',
        'close_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isOpen(): bool
    {
        return $this->is_active && now()->between($this->open_at, $this->close_at);
    }

    public function registrations()
    {
        return $this->hasMany(SidangTaRegistration::class, 'sidang_ta_period_id');
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function scopeOpenForRegistration(Builder $query): Builder
    {
        $at = now();

        return $query
            ->where('is_active', true)
            ->where('open_at', '<=', $at)
            ->where('close_at', '>=', $at)
            ->orderBy('open_at')
            ->orderBy('id');
    }

    public function drafts()
    {
        return $this->hasMany(SidangTaRegistrationDraft::class, 'sidang_ta_period_id');
    }
}
