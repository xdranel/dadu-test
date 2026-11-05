<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class DocumentCategory extends Model
{
    use SoftDeletes, LogsActivity;

    public const VISIBILITY_PUBLIK = 'publik';
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIK   => 'Publik dan Mahasiswa',
        self::VISIBILITY_INTERNAL => 'Pegawai dan Dosen',
    ];

    protected $fillable = [
        'parent_id',
        'depth',
        'name',
        'slug',
        'description',
        'visibility',
        'sort_order',
        'is_pinned',
        'manual_order',
        'show_when_empty',
    ];

    protected $casts = [
        'is_pinned'       => 'boolean',
        'show_when_empty' => 'boolean',
        'sort_order'      => 'integer',
        'manual_order'    => 'integer',
        'depth'           => 'integer',
    ];

    /* ------------------------------------------------------------------ */
    /*  Activity Log Configuration                                        */
    /* ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'parent_id', 'depth', 'is_pinned', 'show_when_empty', 'description', 'visibility'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Folder \"{$this->name}\" telah di-{$eventName}");
    }

    public function getVisibilityBadgeAttribute(): string
    {
        return match(strtolower($this->visibility ?? 'publik')) {
            'publik'    => 'bg-green-100 text-green-800',
            'internal'  => 'bg-blue-100 text-blue-800',
            default     => 'bg-gray-100 text-gray-800',
        };
    }

    public function getVisibilityLabelAttribute(): string
    {
        return match(strtolower($this->visibility ?? 'publik')) {
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

    /* ------------------------------------------------------------------ */
    /*  Boot                                                               */
    /* ------------------------------------------------------------------ */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-generate slug
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }

            // Auto-calculate depth from parent
            if ($model->parent_id) {
                $parent = static::find($model->parent_id);
                $model->depth = $parent ? $parent->depth + 1 : 0;
            } else {
                $model->depth = 0;
            }
        });

        static::updating(function ($model) {
            // Recalculate depth if parent changed
            if ($model->isDirty('parent_id')) {
                if ($model->parent_id) {
                    $parent = static::find($model->parent_id);
                    $model->depth = $parent ? $parent->depth + 1 : 0;
                } else {
                    $model->depth = 0;
                }
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_category_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Accessors & Helpers                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Build ordered array of ancestors from root down to this folder's parent.
     * Efficient for depth ≤ 2 (max 2 iterations).
     */
    public function getAncestorsAttribute(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isSubfolder(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Can this folder have child subfolders?
     * Max depth is 4 (Folder Level 1=0, Level 2=1, Level 3=2, Level 4=3, Level 5=4).
     */
    public function canHaveChildren(): bool
    {
        return $this->depth < 4;
    }

    /**
     * Get maximum depth of any descendant in this category's subtree.
     */
    public function getMaxDescendantDepth(): int
    {
        $maxDepth = $this->depth;
        foreach ($this->children as $child) {
            $maxDepth = max($maxDepth, $child->getMaxDescendantDepth());
        }
        return $maxDepth;
    }

    /**
     * Get total document count including all descendants' documents.
     * Uses a single query with whereIn instead of recursive PHP loops.
     */
    public function getTotalDocumentCountAttribute(): int
    {
        $descendantIds = $this->children()->pluck('id')->toArray();
        $allIds = array_merge([$this->id], $descendantIds);

        return Document::whereIn('document_category_id', $allIds)->count();
    }

    /**
     * Check if moving this folder to $newParentId would create a circular reference.
     */
    public function wouldCreateCycle(int $newParentId): bool
    {
        if ($this->id === $newParentId) {
            return true;
        }

        $parent = static::find($newParentId);
        while ($parent) {
            if ($parent->id === $this->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * Check if moving to given parent would exceed max depth (5 levels: depth 0 to 4).
     */
    public function wouldExceedMaxDepth(?int $newParentId): bool
    {
        if (is_null($newParentId)) {
            return false; // Moving to root is always OK for the folder itself
        }

        $parent = static::find($newParentId);
        if (!$parent) {
            return true;
        }

        $newDepth = $parent->depth + 1;

        // Check this folder itself
        if ($newDepth > 4) {
            return true;
        }

        // If this folder has descendants, check their deepest level
        $depthShift = $newDepth - $this->depth;
        if ($this->getMaxDescendantDepth() + $depthShift > 4) {
            return true;
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, fn($q, $s) =>
            $q->where(function ($inner) use ($s) {
                $inner->where('name', 'like', "%{$s}%")
                      ->orWhere('description', 'like', "%{$s}%");
            })
        );
    }

    /**
     * Urutan folder: tersemat dulu, lalu manual_order (jika ada), lalu alfabetis.
     */
    public function scopePinnedFirst($query)
    {
        return $query->orderByDesc('is_pinned')
                     ->orderByRaw('manual_order IS NULL')
                     ->orderBy('manual_order')
                     ->orderBy('name');
    }
}
