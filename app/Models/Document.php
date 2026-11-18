<?php

namespace App\Models;

use App\Enums\AccessGroup;
use App\Models\Concerns\HasVisibility;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Document extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasVisibility;

    protected $fillable = [
        'document_category_id',
        'title',
        'abbreviation',
        'description',
        'file_name',
        'original_file_name',
        'file_path',
        'storage_disk',
        'checksum',
        'file_type',
        'file_size',
        'visibility',
        'access_group',
        'owner_nim',
        'keywords',
        'document_type',
        'download_count',
    ];

    protected $casts = [
        'access_group'   => AccessGroup::class,
        'owner_nim'      => 'string',
        'download_count' => 'integer',
        'file_size'      => 'integer',
    ];

    /* ------------------------------------------------------------------ */
    /*  Boot                                                               */
    /* ------------------------------------------------------------------ */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Dokumen selalu mewarisi access_group dari foldernya
            if ($model->document_category_id) {
                $category = DocumentCategory::find($model->document_category_id);
                if ($category && $category->access_group) {
                    $model->access_group = $category->access_group;
                }
            } else {
                // Dokumen di root tanpa folder wajib menentukan access_group sendiri
                if (empty($model->access_group)) {
                    throw new \InvalidArgumentException('Dokumen di root tanpa folder wajib menentukan access_group sendiri.');
                }
            }
        });

        static::updating(function ($model) {
            // Jika dokumen berada dalam folder, selalu sinkronkan dengan foldernya
            if ($model->document_category_id) {
                $category = DocumentCategory::find($model->document_category_id);
                if ($category && $category->access_group) {
                    $model->access_group = $category->access_group;
                }
            } else {
                // Dokumen tanpa folder yang baru dipindahkan ke root wajib punya access_group
                if ($model->isDirty('document_category_id') && empty($model->access_group)) {
                    throw new \InvalidArgumentException('Dokumen di root tanpa folder wajib menentukan access_group sendiri.');
                }
            }
        });

        // Force delete menghapus berkas fisik; SoftDelete tidak menghapus berkas
        static::forceDeleted(function ($model) {
            if (!empty($model->file_path) && $model->fileExists()) {
                $model->getStorageDiskInstance()->delete($model->file_path);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Storage & File Resolution Helpers                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Resolusi disk penyimpanan dokumen terpusat.
     * Kolom storage_disk bernilai NULL diperlakukan sebagai 'public' (agar berkas lama tetap berfungsi).
     */
    public function getStorageDisk(): string
    {
        return !empty($this->attributes['storage_disk']) ? (string) $this->attributes['storage_disk'] : 'public';
    }

    /**
     * Instance Filesystem untuk disk penyimpanan dokumen saat ini.
     */
    public function getStorageDiskInstance(): Filesystem
    {
        return Storage::disk($this->getStorageDisk());
    }

    /**
     * Cek apakah berkas fisik ada di disk penyimpanan yang sesuai.
     */
    public function fileExists(): bool
    {
        return !empty($this->file_path) && $this->getStorageDiskInstance()->exists($this->file_path);
    }

    /**
     * Dapatkan path fisik berkas di server jika tersedia.
     */
    public function getPhysicalPath(): ?string
    {
        if (!$this->fileExists()) {
            return null;
        }

        return $this->getStorageDiskInstance()->path($this->file_path);
    }

    /**
     * Tentukan disk penyimpanan yang tepat berdasarkan access_group / visibilitas:
     * - publik -> 'public'
     * - dosen_staf / akademik / mahasiswa -> 'private'
     */
    public static function determineStorageDisk(AccessGroup|string|null $accessGroup, ?string $visibility = null): string
    {
        if ($accessGroup instanceof AccessGroup) {
            return $accessGroup === AccessGroup::PUBLIK ? 'public' : 'private';
        }

        if (is_string($accessGroup) && $accessGroup !== '') {
            return strtolower($accessGroup) === 'publik' ? 'public' : 'private';
        }

        if (is_string($visibility) && $visibility !== '') {
            return strtolower($visibility) === 'publik' ? 'public' : 'private';
        }

        return 'public';
    }

    /**
     * Pindahkan berkas fisik antar disk secara aman: salin dulu, verifikasi ukuran & checksum,
     * baru perbarui database dan hapus yang lama (jika deleteOld=true).
     */
    public function migrateToDisk(string $targetDisk, bool $deleteOld = true): bool
    {
        $currentDisk = $this->getStorageDisk();
        if ($currentDisk === $targetDisk) {
            return true;
        }

        if (empty($this->file_path)) {
            $this->storage_disk = $targetDisk;
            $this->saveQuietly();
            return true;
        }

        $sourceDiskInstance = Storage::disk($currentDisk);
        $targetDiskInstance = Storage::disk($targetDisk);

        if (!$sourceDiskInstance->exists($this->file_path)) {
            $this->storage_disk = $targetDisk;
            $this->saveQuietly();
            return false;
        }

        $fileSize = $sourceDiskInstance->size($this->file_path);
        $stream = $sourceDiskInstance->readStream($this->file_path);
        if ($stream === false) {
            throw new \RuntimeException("Gagal membaca berkas sumber {$this->file_path} dari disk {$currentDisk}");
        }

        $written = $targetDiskInstance->put($this->file_path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$written || !$targetDiskInstance->exists($this->file_path)) {
            throw new \RuntimeException("Gagal menulis berkas {$this->file_path} ke disk {$targetDisk}");
        }

        if ($targetDiskInstance->size($this->file_path) !== $fileSize) {
            $targetDiskInstance->delete($this->file_path);
            throw new \RuntimeException("Verifikasi ukuran berkas gagal saat pemindahan ke disk {$targetDisk}");
        }

        $hashSource = hash('sha256', $sourceDiskInstance->get($this->file_path));
        $hashTarget = hash('sha256', $targetDiskInstance->get($this->file_path));

        if ($hashSource !== $hashTarget) {
            $targetDiskInstance->delete($this->file_path);
            throw new \RuntimeException("Verifikasi checksum berkas gagal saat pemindahan ke disk {$targetDisk}");
        }

        $this->storage_disk = $targetDisk;
        $this->checksum = $hashTarget;
        $this->saveQuietly();

        if ($deleteOld) {
            $sourceDiskInstance->delete($this->file_path);
        }

        return true;
    }

    /**
     * Cek apakah dokumen berkategori publik.
     */
    public function isPublic(): bool
    {
        if ($this->access_group !== null) {
            $ag = $this->access_group instanceof AccessGroup ? $this->access_group : AccessGroup::tryFrom((string)$this->access_group);
            return $ag === AccessGroup::PUBLIK;
        }

        return strtolower($this->visibility ?? 'publik') === 'publik';
    }

    /* ------------------------------------------------------------------ */
    /*  Activity Log Configuration                                        */
    /* ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'document_category_id', 'visibility', 'access_group', 'owner_nim', 'file_name'])
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

    public function nims()
    {
        return $this->hasMany(DocumentNim::class, 'document_id');
    }

    /**
     * Pindahkan dokumen ke folder/kategori baru secara atomik.
     * Mengikuti pewarisan access_group dan pemindahan berkas antar disk jika berbeda root.
     */
    public function moveToCategory(?DocumentCategory $newCategory): void
    {
        DB::transaction(function () use ($newCategory) {
            $newAccessGroup = $newCategory
                ? ($newCategory->getRoot()->access_group instanceof AccessGroup
                    ? $newCategory->getRoot()->access_group->value
                    : $newCategory->getRoot()->access_group)
                : ($this->access_group instanceof AccessGroup ? $this->access_group->value : $this->access_group);

            $targetDisk = self::determineStorageDisk($newAccessGroup, $this->visibility);
            $currentDisk = $this->getStorageDisk();

            if ($currentDisk !== $targetDisk && !empty($this->file_path)) {
                if (Storage::disk($currentDisk)->exists($this->file_path)) {
                    $stream = Storage::disk($currentDisk)->readStream($this->file_path);
                    if ($stream === false) {
                        throw new \RuntimeException("Gagal membaca berkas {$this->file_path}");
                    }
                    $ok = Storage::disk($targetDisk)->put($this->file_path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (!$ok || !Storage::disk($targetDisk)->exists($this->file_path)) {
                        throw new \RuntimeException("Gagal menulis berkas ke disk {$targetDisk}");
                    }

                    $sizeOld = Storage::disk($currentDisk)->size($this->file_path);
                    $sizeNew = Storage::disk($targetDisk)->size($this->file_path);
                    if ($sizeOld !== $sizeNew) {
                        throw new \RuntimeException("Verifikasi ukuran berkas gagal saat pemindahan");
                    }

                    $hashOld = hash('sha256', Storage::disk($currentDisk)->get($this->file_path));
                    $hashNew = hash('sha256', Storage::disk($targetDisk)->get($this->file_path));
                    if ($hashOld !== $hashNew) {
                        throw new \RuntimeException("Verifikasi checksum berkas gagal saat pemindahan");
                    }

                    Storage::disk($currentDisk)->delete($this->file_path);
                    $this->checksum = $hashNew;
                }
                $this->storage_disk = $targetDisk;
            }

            $this->document_category_id = $newCategory?->id;
            if ($newAccessGroup !== null) {
                $this->access_group = $newAccessGroup;
            }
            $this->save();
        });
    }

    /**
     * Nama berkas yang disanitasi untuk unduhan (judul + ekstensi asli).
     * Mencegah karakter berbahaya, zip-slip, dan batas panjang.
     */
    public function getDownloadFilename(): string
    {
        $ext = strtolower(pathinfo($this->file_name ?: ($this->original_file_name ?: ''), PATHINFO_EXTENSION) ?: ($this->file_type ?: ''));
        $title = trim((string) $this->title);
        $safeTitle = preg_replace('~[/\\\:\*\?"<>\|\x00-\x1F]~', '_', $title);
        $safeTitle = trim(preg_replace('/\s+/', ' ', $safeTitle), ". \t\n\r\0\x0B");
        if ($safeTitle === '') {
            $safeTitle = 'dokumen';
        }
        $safeTitle = mb_substr($safeTitle, 0, 150);

        return $ext !== '' ? "{$safeTitle}.{$ext}" : $safeTitle;
    }

    /**
     * Header Content-Disposition berstandar RFC 5987 / RFC 6266.
     */
    public function getContentDisposition(string $disposition = 'attachment'): string
    {
        $filename = $this->getDownloadFilename();
        $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $asciiName = str_replace('"', '', $asciiName);
        $encodedName = rawurlencode($filename);

        return "{$disposition}; filename=\"{$asciiName}\"; filename*=UTF-8''{$encodedName}";
    }

    /**
     * Normalisasi format NIM: trim, wajib hanya digit, panjang dalam rentang
     * config documents.nim_min_length (default 8) dan nim_max_length (default 15).
     * String persis, leading zero aman. Tolak selain itu.
     */
    public static function normalizeNim(string $nim): string
    {
        $trimmed = trim($nim);
        $min = (int) config('documents.nim_min_length', 8);
        $max = (int) config('documents.nim_max_length', 15);

        if (!preg_match('/^\d+$/', $trimmed) || strlen($trimmed) < $min || strlen($trimmed) > $max) {
            throw new \InvalidArgumentException("NIM tidak valid: '{$nim}'. NIM harus berupa digit angka dengan panjang {$min}-{$max} karakter.");
        }
        return $trimmed;
    }

    /**
     * Sinkronisasi relasi NIM dokumen ke tabel pivot document_nim.
     * Mencatat perubahan penambahan dan penghapusan NIM ke activity log.
     */
    public function syncNims(array|string $nims, ?User $actor = null): array
    {
        if (is_string($nims)) {
            $parts = preg_split('/[;, \r\n]+/', trim($nims));
            $nims = array_filter($parts, fn($v) => $v !== '');
        }

        $normalized = [];
        foreach ($nims as $item) {
            $normalized[] = self::normalizeNim((string) $item);
        }
        $normalized = array_values(array_unique($normalized));

        $existing = $this->nims()->pluck('nim')->toArray();

        $toAdd = array_values(array_diff($normalized, $existing));
        $toRemove = array_values(array_diff($existing, $normalized));

        if (!empty($toRemove)) {
            $this->nims()->whereIn('nim', $toRemove)->delete();
        }

        if (!empty($toAdd)) {
            $records = array_map(function ($nim) {
                return [
                    'document_id' => $this->id,
                    'nim'         => $nim,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }, $toAdd);
            DocumentNim::insert($records);
        }

        if (!empty($toAdd) || !empty($toRemove)) {
            activity('document_nim_sync')
                ->performedOn($this)
                ->causedBy($actor ?? auth()->user())
                ->withProperties([
                    'document_id'  => $this->id,
                    'title'        => $this->title,
                    'added_nims'   => $toAdd,
                    'removed_nims' => $toRemove,
                    'total_nims'   => count($normalized),
                ])
                ->log("Sinkronisasi NIM dokumen: +" . count($toAdd) . " NIM, -" . count($toRemove) . " NIM");
        }

        return [
            'added'   => $toAdd,
            'removed' => $toRemove,
            'current' => $normalized,
        ];
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

    /**
     * Scope terpusat dokumen yang dapat dilihat oleh user/guest.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Kondisi untuk dokumen yang SUDAH memiliki access_group (access_group IS NOT NULL)
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

                // Mahasiswa: publik + dokumen mahasiswa yang NIM miliknya ada di pivot document_nim
                if ($user->hasRole(['mahasiswa', 'Mahasiswa'])) {
                    $nim = $user->nim;

                    $sub->where(function ($mq) use ($nim) {
                        $mq->where('access_group', AccessGroup::PUBLIK->value);

                        if ($nim !== null && $nim !== '') {
                            $mq->orWhere(function ($own) use ($nim) {
                                $own->where('access_group', AccessGroup::MAHASISWA->value)
                                    ->whereHas('nims', function ($nq) use ($nim) {
                                        $nq->where('nim', (string) $nim);
                                    });
                            });
                        }
                    });
                    return;
                }

                // User tanpa role dikenal: hanya publik
                $sub->where('access_group', AccessGroup::PUBLIK->value);
            });

            // ATAU kondisi fallback untuk dokumen dengan access_group NULL (visibilitas lama)
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

    public const VISIBILITY_PUBLIK = 'publik';
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_PRIVATE = 'private';

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
}
