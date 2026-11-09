<?php

namespace App\Services;

use App\Models\SidangTaFormGuide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class SidangTaFormGuideService
{
    /** @return Collection<string, Collection<int, SidangTaFormGuide>> */
    public function activeForSlots(array $slots): Collection
    {
        return SidangTaFormGuide::where('is_active', true)->whereIn('slot', $slots)
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('slot');
    }

    public function save(SidangTaFormGuide $guide, array $attributes, ?UploadedFile $file = null): SidangTaFormGuide
    {
        return DB::transaction(function () use ($guide, $attributes, $file) {
            // Serialize replacements so concurrent requests clean only the replaced version.
            if ($guide->exists) {
                $guide = SidangTaFormGuide::whereKey($guide->id)->lockForUpdate()->firstOrFail();
            }
            $oldPath = $guide->file_path;
            if ($file) {
                try {
                    $path = $file->store('sidang-ta/form-guides', 'local');
                    if (! $path) {
                        throw UnableToWriteFile::atLocation('sidang-ta/form-guides', 'Storage returned false.');
                    }
                } catch (FilesystemException $exception) {
                    Log::warning('Penyimpanan berkas panduan Sidang TA gagal.', [
                        'disk' => 'local', 'directory' => 'sidang-ta/form-guides',
                        'exception' => $exception::class, 'error' => $exception->getMessage(),
                    ]);
                    throw ValidationException::withMessages([
                        'file' => 'Berkas panduan gagal disimpan. Hubungi administrator untuk memeriksa penyimpanan server.',
                    ]);
                }
                DB::afterRollBack(fn () => $this->removeFile($path));
                $attributes = array_replace($attributes, ['file_path' => $path,
                    'file_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType()]);
            }
            $guide->fill($attributes);
            $guide->save();
            if ($file && $oldPath) {
                DB::afterCommit(fn () => $this->removeFile($oldPath));
            }

            return $guide;
        });
    }

    public function delete(SidangTaFormGuide $guide): void
    {
        DB::transaction(function () use ($guide) {
            $guide = SidangTaFormGuide::whereKey($guide->id)->lockForUpdate()->firstOrFail();
            $path = $guide->file_path;
            // Retain the seed key tombstone so initialization respects deliberate removal.
            $guide->delete();
            if ($path) {
                DB::afterCommit(fn () => $this->removeFile($path));
            }
        });
    }

    private function removeFile(string $path): void
    {
        try {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('Berkas panduan gagal dihapus.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            Log::warning('Berkas panduan gagal dihapus.', ['path' => $path, 'error' => $exception->getMessage()]);
        }
    }
}
