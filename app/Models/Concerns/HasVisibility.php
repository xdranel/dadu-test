<?php

namespace App\Models\Concerns;

trait HasVisibility
{
    public function getVisibilityMetaAttribute(): array
    {
        return config('documents.visibility.' . $this->visibility)
            ?? config('documents.visibility.publik');
    }

    public function getVisibilityLabelAttribute(): string
    {
        return $this->visibility_meta['label'] ?? ucfirst((string) $this->visibility);
    }

    public function getVisibilityBadgeAttribute(): string
    {
        return $this->visibility_meta['badge'] ?? 'bg-slate-50 text-slate-700 border border-slate-200/60';
    }

    public function getVisibilityIconUrlAttribute(): string
    {
        return asset(config('documents.asset_dir') . '/' . ($this->visibility_meta['icon'] ?? 'publicVisibility.svg'));
    }

    public function setVisibilityAttribute($value): void
    {
        $val = strtolower((string) $value);
        $allowed = array_keys(config('documents.visibility', []));

        if (in_array($val, $allowed, true)) {
            $this->attributes['visibility'] = $val;
        } elseif (in_array($val, ['pegawai', 'dosen'])) {
            $this->attributes['visibility'] = 'internal';
        } elseif ($val === 'privat') {
            $this->attributes['visibility'] = 'private';
        } else {
            $this->attributes['visibility'] = 'publik';
        }
    }
}
