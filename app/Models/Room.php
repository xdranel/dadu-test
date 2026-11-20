<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const LABEL_CLASSROOM = 'KELAS';

    public const LABELS = [self::LABEL_CLASSROOM, 'LAB'];

    protected $fillable = [
        'room_code',
        'name',
        'building',
        'capacity',
        'label',
        'status',
    ];

    public function scopeBookableClassrooms($query)
    {
        return $query->where('label', self::LABEL_CLASSROOM)->where('status', 'Dapat Dipakai');
    }

    public function isBookableClassroom(): bool
    {
        return $this->label === self::LABEL_CLASSROOM && $this->status === 'Dapat Dipakai';
    }

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
