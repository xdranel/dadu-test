<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'room_code',
        'name',
        'building',
        'capacity',
        'status',
        'is_classroom',
    ];

    protected $casts = ['is_classroom' => 'boolean'];

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn ($q, $s) => $q->where(function ($inner) use ($s) {
            $inner->where('name', 'like', "%{$s}%")
                ->orWhere('room_code', 'like', "%{$s}%");
        })
        );
    }

    public function scopeFilterBuilding($query, ?string $building)
    {
        return $query->when($building, fn ($q, $g) => $q->where('building', $g));
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        return $query->when($status, fn ($q, $s) => $q->where('status', $s));
    }
}
