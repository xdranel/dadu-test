<?php

namespace App\Models;

use App\Enums\AccessGroup;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class DocumentCategory extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasVisibility;

    public const VISIBILITY_PUBLIK = 'publik';
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'parent_id',
        'depth',
        'name',
        'slug',
        'description',
        'visibility',
        'access_group',
        'sort_order',
        'is_pinned',
        'manual_order',
        'show_when_empty',
    ];

    protected $casts = [
        'access_group'    => AccessGroup::class,
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
            ->logOnly(['name', 'parent_id', 'depth', 'is_pinned', 'show_when_empty', 'description', 'visibility', 'access_group'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Folder \"{$this->name}\" telah di-{$eventName}");
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

            // Auto-calculate depth from parent & inherit access_group from root
            if ($model->parent_id) {
                $parent = static::find($model->parent_id);
                $model->depth = $parent ? $parent->depth + 1 : 0;

                $root = $model->getRoot();
                if ($root && $root->access_group) {
                    $model->access_group = $root->access_group;
                }
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

            // Subfolder selalu mewarisi dari root dan tidak boleh diubah secara independen
            if ($model->parent_id !== null && ! $model->isDirty('parent_id') && $model->isDirty('access_group')) {
                throw new \DomainException('Access group subfolder tidak dapat diubah secara langsung karena selalu mewarisi dari root.');
            }
        });

        static::updated(function ($model) {
            // Jika root folder mengubah access_group, update seluruh turunan (subfolder + dokumen)
            if ($model->isRoot() && $model->wasChanged('access_group')) {
                $rawVal = $model->access_group instanceof AccessGroup ? $model->access_group->value : $model->access_group;
                if ($rawVal !== null) {
                    $descendantIds = $model->descendantIds();
                    if ($descendantIds->isNotEmpty()) {
                        static::whereIn('id', $descendantIds)->update(['access_group' => $rawVal]);
                        Document::whereIn('document_category_id', $descendantIds)->update(['access_group' => $rawVal]);
                    }
                    Document::where('document_category_id', $model->id)->update(['access_group' => $rawVal]);
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
     * Iteratif dan aman dari siklus/loop tak berujung.
     */
    public function getAncestorsAttribute(): Collection
    {
        return $this->ancestorsList();
    }

    public function ancestorsList(): Collection // root -> induk, iteratif
    {
        $chain = collect();
        $node = $this->parent;
        $guard = 0;
        while ($node && $guard++ < 1000) {
            $chain->prepend($node);
            $node = $node->parent;
        }
        return $chain;
    }

    /**
     * Helper turunan folder tanpa rekursi PHP (iteratif, aman untuk level sangat dalam).
     */
    public function descendantIds(): Collection
    {
        $all = collect();
        $level = collect([$this->id]);
        while ($level->isNotEmpty()) {
            $level = static::whereIn('parent_id', $level)->pluck('id')->diff($all); // diff mencegah loop tak berujung jika data rusak
            $all = $all->merge($level);
        }
        return $all;
    }

    /**
     * Pencegahan siklus (folder tidak boleh dipindah ke dalam dirinya sendiri atau turunannya).
     */
    public function wouldCreateCycle(?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }
        return $newParentId === $this->id || $this->descendantIds()->contains($newParentId);
    }

    /**
     * Accessor full_path: nama path lengkap (mis. "A / B / C").
     */
    public function getFullPathAttribute(): string
    {
        $ancestorNames = $this->ancestorsList()->pluck('name');
        return $ancestorNames->push($this->name)->implode(' / ');
    }

    /**
     * Accessor full_path: peta statis id => 'A / B / C' untuk mencegah N+1.
     */
    public static function pathMap(): array // [id => 'A / B / C']
    {
        $rows = static::get(['id', 'name', 'parent_id'])->keyBy('id');
        $paths = [];
        foreach ($rows as $id => $row) {
            $parts = [];
            $cur = $row;
            $guard = 0;
            while ($cur && $guard++ < 1000) {
                array_unshift($parts, $cur->name);
                $cur = $rows->get($cur->parent_id);
            }
            $paths[$id] = implode(' / ', $parts);
        }
        return $paths;
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
     * Cek apakah folder berkategori publik.
     */
    public function isPublic(): bool
    {
        if ($this->access_group !== null) {
            $ag = $this->access_group instanceof AccessGroup ? $this->access_group : AccessGroup::tryFrom((string)$this->access_group);
            return $ag === AccessGroup::PUBLIK;
        }

        return strtolower($this->visibility ?? 'publik') === 'publik';
    }

    /**
     * Dapatkan folder paling atas (root) dari pohon kategori ini.
     */
    public function getRoot(): self
    {
        $node = $this;
        $guard = 0;
        while ($node->parent_id !== null && $guard++ < 1000) {
            $parent = $node->parent ?? static::find($node->parent_id);
            if (! $parent || $parent->id === $node->id) {
                break;
            }
            $node = $parent;
        }
        return $node;
    }

    /**
     * Dapatkan path lengkap folder dari root dipisahkan oleh " / ".
     */
    public function getFullPath(): string
    {
        $names = [$this->name];
        $curr = $this;
        $guard = 0;
        while ($curr->parent_id !== null && $guard++ < 1000) {
            $parent = $curr->parent ?? static::find($curr->parent_id);
            if (! $parent || $parent->id === $curr->id) {
                break;
            }
            array_unshift($names, $parent->name);
            $curr = $parent;
        }
        return implode(' / ', $names);
    }

    /**
     * Cari folder berdasarkan path lengkap "Root / Sub / Leaf".
     * Mengembalikan null bila tidak ditemukan atau ambigu.
     */
    public static function findByFullPath(string $path): ?self
    {
        $segments = array_filter(array_map('trim', explode('/', $path)), fn($v) => $v !== '');
        if (empty($segments)) {
            return null;
        }

        $currentParentId = null;
        $currentCategory = null;

        foreach ($segments as $segment) {
            $query = static::where('name', $segment);
            if ($currentParentId === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $currentParentId);
            }

            $matches = $query->get();
            if ($matches->count() !== 1) {
                return null;
            }

            $currentCategory = $matches->first();
            $currentParentId = $currentCategory->id;
        }

        return $currentCategory;
    }

    /**
     * Pindah folder ke parent baru (atau ke root).
     * Memperbarui access_group seluruh turunannya (subfolder + dokumen) dalam satu transaksi.
     */
    public function moveTo(?int $newParentId): void
    {
        if ($this->wouldCreateCycle($newParentId)) {
            throw new \InvalidArgumentException('Folder tidak bisa dipindah ke dalam dirinya sendiri atau subfoldernya.');
        }

        DB::transaction(function () use ($newParentId) {
            $newParent = $newParentId ? static::findOrFail($newParentId) : null;
            $newDepth = $newParent ? $newParent->depth + 1 : 0;
            $delta = $newDepth - $this->depth;

            // Jika dipindah ke dalam folder lain, ambil access_group dari root tujuan
            // Jika dipindah ke root (null), pertahankan access_group saat ini
            $newAccessGroup = $newParent
                ? ($newParent->getRoot()->access_group instanceof AccessGroup ? $newParent->getRoot()->access_group->value : $newParent->getRoot()->access_group)
                : ($this->access_group instanceof AccessGroup ? $this->access_group->value : $this->access_group);

            $descendantIds = $this->descendantIds();
            $allFolderIds = $descendantIds->push($this->id);

            // Update self
            $this->parent_id = $newParentId;
            $this->depth = $newDepth;
            $this->access_group = $newAccessGroup;
            $this->saveQuietly();

            if ($delta !== 0 && $descendantIds->isNotEmpty()) {
                static::whereIn('id', $descendantIds)->update([
                    'depth' => DB::raw('depth + (' . (int) $delta . ')'),
                ]);
            }

            if ($newAccessGroup !== null) {
                if ($descendantIds->isNotEmpty()) {
                    static::whereIn('id', $descendantIds)->update(['access_group' => $newAccessGroup]);
                }

                $targetDisk = Document::determineStorageDisk($newAccessGroup);
                $docs = Document::whereIn('document_category_id', $allFolderIds)->get();

                $oldFilesToDelete = [];
                $newFilesCreated = [];

                try {
                    foreach ($docs as $doc) {
                        $doc->access_group = $newAccessGroup;
                        $oldDisk = $doc->getStorageDisk();
                        if ($oldDisk !== $targetDisk && !empty($doc->file_path)) {
                            if (Storage::disk($oldDisk)->exists($doc->file_path)) {
                                $stream = Storage::disk($oldDisk)->readStream($doc->file_path);
                                if ($stream === false) {
                                    throw new \RuntimeException("Gagal membaca berkas {$doc->file_path}");
                                }
                                $ok = Storage::disk($targetDisk)->put($doc->file_path, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                                if (!$ok || !Storage::disk($targetDisk)->exists($doc->file_path)) {
                                    throw new \RuntimeException("Gagal menulis berkas ke disk {$targetDisk}");
                                }

                                $sizeOld = Storage::disk($oldDisk)->size($doc->file_path);
                                $sizeNew = Storage::disk($targetDisk)->size($doc->file_path);
                                if ($sizeOld !== $sizeNew) {
                                    throw new \RuntimeException("Verifikasi ukuran berkas gagal saat pemindahan");
                                }

                                $hashOld = hash('sha256', Storage::disk($oldDisk)->get($doc->file_path));
                                $hashNew = hash('sha256', Storage::disk($targetDisk)->get($doc->file_path));
                                if ($hashOld !== $hashNew) {
                                    throw new \RuntimeException("Verifikasi checksum berkas gagal saat pemindahan");
                                }

                                $newFilesCreated[] = ['disk' => $targetDisk, 'path' => $doc->file_path];
                                $oldFilesToDelete[] = ['disk' => $oldDisk, 'path' => $doc->file_path];
                                $doc->checksum = $hashNew;
                            }
                            $doc->storage_disk = $targetDisk;
                        }
                        $doc->saveQuietly();
                    }
                } catch (\Throwable $e) {
                    foreach ($newFilesCreated as $created) {
                        Storage::disk($created['disk'])->delete($created['path']);
                    }
                    throw $e;
                }

                foreach ($oldFilesToDelete as $old) {
                    Storage::disk($old['disk'])->delete($old['path']);
                }
            }
        });
    }

    /**
     * Subfolder tidak dibatasi tingkat kedalamannya.
     */
    public function canHaveChildren(): bool
    {
        return true;
    }

    /**
     * Get maximum depth of any descendant in this category's subtree (iteratif).
     */
    public function getMaxDescendantDepth(): int
    {
        $ids = $this->descendantIds();
        if ($ids->isEmpty()) {
            return $this->depth;
        }
        return (int) (static::whereIn('id', $ids)->max('depth') ?? $this->depth);
    }

    /**
     * Get total document count including all descendants' documents.
     */
    public function getTotalDocumentCountAttribute(): int
    {
        return Document::whereIn('document_category_id', $this->descendantIds()->push($this->id))->count();
    }

    /**
     * Cek apakah pemindahan melebihi batas langit-langit keamanan opsional (config documents.max_depth).
     * Jika config bernilai null, maka tidak ada batas (selalu false).
     */
    public function wouldExceedMaxDepth(?int $newParentId): bool
    {
        $maxDepth = config('documents.max_depth');
        if ($maxDepth === null || $newParentId === null) {
            return false;
        }

        $parent = static::find($newParentId);
        if (!$parent) {
            return true;
        }

        $newDepth = $parent->depth + 1;
        if ($newDepth > $maxDepth) {
            return true;
        }

        $depthShift = $newDepth - $this->depth;
        $maxChildDepth = static::whereIn('id', $this->descendantIds())->max('depth') ?? $this->depth;

        return ($maxChildDepth + $depthShift) > $maxDepth;
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
     * Scope terpusat kategori yang dapat dilihat oleh user/guest.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Kondisi untuk kategori yang SUDAH memiliki access_group (access_group IS NOT NULL)
            $q->where(function ($sub) use ($user) {
                $sub->whereNotNull('access_group');

                if ($user === null) {
                    $sub->where('access_group', AccessGroup::PUBLIK->value);
                    return;
                }

                // Admin & Akademik: melihat semua
                if ($user->hasRole(['admin', 'Akademik', 'Admin'])) {
                    return;
                }

                // Pelaksana, Pemimpin, Pengamat, Petugas: publik + dosen_staf
                if ($user->hasRole(['Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat', 'Petugas', 'petugas'])) {
                    $sub->whereIn('access_group', [
                        AccessGroup::PUBLIK->value,
                        AccessGroup::DOSEN_STAF->value,
                    ]);
                    return;
                }

                // Mahasiswa: hanya publik di explorer folder (folder mahasiswa disembunyikan)
                if ($user->hasRole(['mahasiswa', 'Mahasiswa'])) {
                    $sub->where('access_group', AccessGroup::PUBLIK->value);
                    return;
                }

                // User tanpa role dikenal: hanya publik
                $sub->where('access_group', AccessGroup::PUBLIK->value);
            });

            // ATAU kondisi fallback untuk kategori dengan access_group NULL (visibilitas lama)
            $q->orWhere(function ($sub) use ($user) {
                $sub->whereNull('access_group');

                if ($user === null) {
                    $sub->where('visibility', 'publik');
                    return;
                }

                if ($user->hasRole(['admin', 'Akademik', 'Admin'])) {
                    return;
                }

                if ($user->hasRole(['Pelaksana', 'Pemimpin', 'Pengamat', 'pelaksana', 'pemimpin', 'pengamat', 'Petugas', 'petugas'])) {
                    $sub->whereIn('visibility', ['publik', 'internal']);
                    return;
                }

                $sub->where('visibility', 'publik');
            });
        });
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
