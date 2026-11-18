<?php

namespace App\Console\Commands;

use App\Enums\AccessGroup;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:migrate-storage
                            {--dry-run : Menjalankan simulasi tanpa menyalin berkas atau mengubah database}
                            {--limit= : Batasi jumlah dokumen yang diproses}
                            {--cleanup : Menghapus berkas salinan lama di disk public setelah terverifikasi di private}
                            {--force : Lewati konfirmasi interaktif pada opsi cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrasi berkas dokumen non-publik dari disk public ke disk private secara aman dan terverifikasi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $cleanup = (bool) $this->option('cleanup');
        $force = (bool) $this->option('force');

        if ($cleanup) {
            return $this->handleCleanup($dryRun, $force);
        }

        return $this->handleMigration($dryRun, $limit);
    }

    /**
     * Menjalankan migrasi penyalinan berkas non-publik ke disk private.
     */
    protected function handleMigration(bool $dryRun, ?int $limit): int
    {
        $this->info($dryRun
            ? '=== Memulai Migrasi Storage Dokumen Non-Publik (MODE DRY-RUN) ==='
            : '=== Memulai Migrasi Storage Dokumen Non-Publik ==='
        );

        $query = Document::where(function ($q) {
            $q->where(function ($sq) {
                $sq->whereNotNull('access_group')
                   ->where('access_group', '!=', AccessGroup::PUBLIK->value);
            })->orWhere(function ($sq) {
                $sq->whereNull('access_group')
                   ->whereIn('visibility', ['internal', 'private']);
            });
        })->where(function ($q) {
            $q->whereNull('storage_disk')
              ->orWhere('storage_disk', '!=', 'private');
        });

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $totalCandidate = $query->count();
        $this->info("Ditemukan {$totalCandidate} dokumen non-publik yang berada di disk public.");

        if ($totalCandidate === 0) {
            $this->info('Tidak ada dokumen yang perlu dimigrasikan.');
            return 0;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        $sourceDisk = Storage::disk('public');
        $targetDisk = Storage::disk('private');

        $documents = $limit && $limit > 0 ? $query->get() : $query->lazyById(100);

        foreach ($documents as $doc) {
            if (empty($doc->file_path)) {
                $this->warn("- Dokumen ID {$doc->id} ({$doc->title}): dilewati (path file kosong).");
                $skipped++;
                continue;
            }

            if (! $sourceDisk->exists($doc->file_path)) {
                $this->warn("- Dokumen ID {$doc->id} ({$doc->title}): berkas tidak ditemukan di disk public ({$doc->file_path}).");
                $skipped++;
                continue;
            }

            $sourceSize = (int) $sourceDisk->size($doc->file_path);
            $sourceContent = $sourceDisk->get($doc->file_path);
            $sourceChecksum = hash('sha256', $sourceContent);

            if ($dryRun) {
                $this->line("[DRY-RUN] Akan menyalin dokumen ID {$doc->id} ({$doc->title}) [{$doc->file_path}] ke disk private.");
                $copied++;
                continue;
            }

            try {
                $stream = $sourceDisk->readStream($doc->file_path);
                if ($stream === false) {
                    throw new \RuntimeException("Gagal membuka stream berkas sumber");
                }

                $written = $targetDisk->put($doc->file_path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $written || ! $targetDisk->exists($doc->file_path)) {
                    throw new \RuntimeException("Gagal menulis berkas ke disk private");
                }

                // Verifikasi ukuran berkas
                $targetSize = (int) $targetDisk->size($doc->file_path);
                if ($targetSize !== $sourceSize) {
                    $targetDisk->delete($doc->file_path);
                    throw new \RuntimeException("Ukuran berkas tidak cocok (sumber: {$sourceSize}, target: {$targetSize})");
                }

                // Verifikasi checksum SHA-256
                $targetChecksum = hash('sha256', $targetDisk->get($doc->file_path));
                if ($targetChecksum !== $sourceChecksum) {
                    $targetDisk->delete($doc->file_path);
                    throw new \RuntimeException("Checksum tidak cocok");
                }

                // Update database: storage_disk='private' dan catat checksum
                $doc->storage_disk = 'private';
                $doc->checksum = $targetChecksum;
                $doc->saveQuietly();

                $this->info("✓ Dokumen ID {$doc->id} ({$doc->title}) berhasil disalin dan diverifikasi di disk private.");
                $copied++;
            } catch (\Throwable $e) {
                $this->error("✗ Dokumen ID {$doc->id} ({$doc->title}) gagal: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("=== Ringkasan Migrasi Storage ===");
        $this->info("Berhasil disalin  : {$copied}");
        $this->info("Dilewati (hilang) : {$skipped}");
        $this->info("Gagal verifikasi  : {$failed}");

        // Catat ke activity log jika bukan dry-run
        if (! $dryRun) {
            activity('storage_migration')
                ->withProperties([
                    'action'   => 'migrate_to_private',
                    'copied'   => $copied,
                    'skipped'  => $skipped,
                    'failed'   => $failed,
                    'dry_run'  => false,
                ])
                ->log("Migrasi penyimpanan dokumen non-publik: {$copied} disalin, {$skipped} dilewati, {$failed} gagal");
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Menghapus berkas salinan lama di disk public untuk dokumen yang SUDAH terverifikasi di private.
     */
    protected function handleCleanup(bool $dryRun, bool $force): int
    {
        $this->warn('=== Mode Cleanup: Pembersihan Salinan Berkas Lama di Disk Public ===');

        if (! $dryRun && ! $force) {
            $confirmed = $this->confirm(
                'Apakah Anda yakin ingin menghapus berkas salinan lama di disk public untuk dokumen yang sudah terverifikasi di disk private?',
                false
            );

            if (! $confirmed) {
                $this->info('Operasi cleanup dibatalkan.');
                return 0;
            }
        }

        $query = Document::where('storage_disk', 'private')
            ->whereNotNull('file_path');

        $totalCandidate = $query->count();
        $this->info("Memeriksa {$totalCandidate} dokumen yang berstatus disk private...");

        $deleted = 0;
        $skipped = 0;

        $sourceDisk = Storage::disk('public');
        $targetDisk = Storage::disk('private');

        foreach ($query->lazyById(100) as $doc) {
            // Pastikan file benar-benar ada dan terverifikasi di disk private sebelum menghapus di public
            if (! $targetDisk->exists($doc->file_path)) {
                $this->warn("- Dokumen ID {$doc->id}: dilewati, berkas fisik tidak ditemukan di disk private.");
                $skipped++;
                continue;
            }

            if (! $sourceDisk->exists($doc->file_path)) {
                // Sudah tidak ada di public, tidak perlu dihapus lagi
                $skipped++;
                continue;
            }

            // Verifikasi ukuran target private cocok
            $privateSize = (int) $targetDisk->size($doc->file_path);
            $publicSize = (int) $sourceDisk->size($doc->file_path);
            if ($privateSize !== $publicSize) {
                $this->error("- Dokumen ID {$doc->id}: dilewati, ukuran berkas di public dan private tidak cocok.");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Akan menghapus salinan public: {$doc->file_path}");
                $deleted++;
                continue;
            }

            $sourceDisk->delete($doc->file_path);
            $this->info("✓ Salinan public dihapus: {$doc->file_path}");
            $deleted++;
        }

        $this->newLine();
        $this->info("=== Ringkasan Cleanup ===");
        $this->info("Salinan public dihapus : {$deleted}");
        $this->info("Dilewati               : {$skipped}");

        if (! $dryRun && $deleted > 0) {
            activity('storage_migration')
                ->withProperties([
                    'action'  => 'cleanup_public_copies',
                    'deleted' => $deleted,
                    'skipped' => $skipped,
                    'dry_run' => false,
                ])
                ->log("Pembersihan salinan lama di disk public selesai: {$deleted} berkas dihapus, {$skipped} dilewati");
        }

        return 0;
    }
}
