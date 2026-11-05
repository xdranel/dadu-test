<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Document extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'document_category_id',
        'title',
        'abbreviation',
        'description',
        'file_name',
        'original_file_name',
        'file_path',
        'file_type',
        'file_size',
        'visibility',
        'download_count',
    ];

    /* ------------------------------------------------------------------ */
    /*  Activity Log Configuration                                        */
    /* ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'document_category_id', 'visibility', 'file_name'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Dokumen \"{$this->title}\" telah di-{$eventName}");
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('title', 'like', "%{$s}%")
                      ->orWhere('abbreviation', 'like', "%{$s}%")
                      ->orWhere('description', 'like', "%{$s}%");
            })
        );
    }

    public function scopeFilterCategory($query, ?string $category)
    {
        return $query->when($category, fn($q, $k) =>
            $q->where('document_category_id', $k)
        );
    }

    public function scopeFilterVisibility($query, ?string $visibility)
    {
        return $query->when($visibility, fn($q, $v) =>
            $q->where('visibility', $v)
        );
    }

    public const VISIBILITY_PUBLIK = 'publik';
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIK   => 'Publik dan Mahasiswa',
        self::VISIBILITY_INTERNAL => 'Pegawai dan Dosen',
    ];

    /* ------------------------------------------------------------------ */
    /*  Accessors                                                          */
    /* ------------------------------------------------------------------ */

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function getVisibilityBadgeAttribute(): string
    {
        return match(strtolower($this->visibility ?? '')) {
            'publik'    => 'bg-green-100 text-green-800',
            'internal'  => 'bg-blue-100 text-blue-800',
            default     => 'bg-gray-100 text-gray-800',
        };
    }

    public function getVisibilityLabelAttribute(): string
    {
        return match(strtolower($this->visibility ?? '')) {
            'publik'    => 'Publik dan Mahasiswa',
            'internal'  => 'Pegawai dan Dosen',
            default     => ucfirst((string) $this->visibility),
        };
    }

    public function setVisibilityAttribute($value): void
    {
        $val = strtolower((string) $value);
        if (in_array($val, ['pegawai', 'privat', 'dosen', 'internal'])) {
            $val = self::VISIBILITY_INTERNAL;
        } else {
            $val = self::VISIBILITY_PUBLIK;
        }
        $this->attributes['visibility'] = $val;
    }
}
