<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SidangTaPeriod extends Model
{
    protected $fillable = ['name', 'open_at', 'close_at', 'revision_open_at', 'revision_close_at', 'is_active', 'description'];

    protected $casts = [
        'open_at' => 'datetime',
        'close_at' => 'datetime',
        'revision_open_at' => 'datetime',
        'revision_close_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isOpen(): bool
    {
        return $this->is_active && now()->between($this->open_at, $this->close_at);
    }

    public function revisionIsConfigured(): bool
    {
        return $this->revision_open_at !== null && $this->revision_close_at !== null;
    }

    public function revisionIsOpen(): bool
    {
        return !$this->revisionIsConfigured() || now()->between($this->revision_open_at, $this->revision_close_at);
    }

    public function revisionHasExpired(): bool
    {
        return $this->revisionIsConfigured() && now()->greaterThan($this->revision_close_at);
    }

    public function scopeRevisionExpired(Builder $query): Builder
    {
        return $query->whereNotNull('revision_open_at')->whereNotNull('revision_close_at')
            ->where('revision_close_at', '<', now());
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
