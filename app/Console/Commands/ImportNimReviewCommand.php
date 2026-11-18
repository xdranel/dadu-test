<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentNim;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class ImportNimReviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:import-nim-review
                            {file : Path ke file CSV hasil review (relatif ke disk private atau path absolut)}
                            {--dry-run : Menjalankan validasi tanpa mengubah database}
                            {--allow-unknown : Mengizinkan impor NIM yang tidak terdaftar pada data mahasiswa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memuat hasil review NIM dokumen dari CSV ke tabel pivot document_nim';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $allowUnknown = (bool) $this->option('allow-unknown');

        $this->info($dryRun
            ? "=== Memulai Import Review NIM (MODE DRY-RUN) ==="
            : "=== Memulai Import Review NIM ==="
        );

        $content = null;
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
        } elseif (Storage::disk('private')->exists($filePath)) {
            $content = Storage::disk('private')->get($filePath);
        } elseif (Storage::disk('public')->exists($filePath)) {
            $content = Storage::disk('public')->get($filePath);
        }

        if ($content === null || $content === false) {
            $this->error("File CSV tidak ditemukan: {$filePath}");
            return 1;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            $this->warn("File CSV kosong.");
            return 0;
        }

        // Cache daftar NIM mahasiswa valid dari tabel users
        $verifiedNims = [];
        if (Schema::hasTable('users') && Role::whereIn('name', ['mahasiswa', 'Mahasiswa'])->exists()) {
            $verifiedNims = User::whereHas('roles', fn($q) => $q->whereIn('name', ['mahasiswa', 'Mahasiswa']))
                ->whereNotNull('employee_id')
                ->where('employee_id', '!=', '')
                ->pluck('employee_id')
                ->map(fn($v) => (string) $v)
                ->flip()
                ->toArray();
        }

        $successCount = 0;
        $invalidCount = 0;
        $totalNimsLinked = 0;

        $header = null;
        $idIdx = 0;
        $nimIdx = 1;

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line);

            if ($header === null) {
                // Header detection
                $firstCell = strtolower(trim($row[0] ?? ''));
                if ($firstCell === 'id' || str_contains($firstCell, 'id')) {
                    $header = array_map(fn($h) => strtolower(trim($h)), $row);
                    foreach ($header as $colIdx => $colName) {
                        if ($colName === 'id') $idIdx = $colIdx;
                        if (str_contains($colName, 'nim')) $nimIdx = $colIdx;
                    }
                    continue;
                }
                $header = []; // Tidak ada header, langsung baris data
            }

            $rawId = trim($row[$idIdx] ?? '');
            $rawNims = trim($row[$nimIdx] ?? '');

            if ($rawId === '' || ! is_numeric($rawId)) {
                $this->warn("Baris " . ($i + 1) . ": ID dokumen tidak valid ('{$rawId}'). Dilewati.");
                $invalidCount++;
                continue;
            }

            $doc = Document::find($rawId);
            if (! $doc) {
                $this->warn("Baris " . ($i + 1) . ": Dokumen ID {$rawId} tidak ditemukan di database. Dilewati.");
                $invalidCount++;
                continue;
            }

            if ($rawNims === '' || $rawNims === '-') {
                $this->warn("Baris " . ($i + 1) . ": Dokumen ID {$rawId} tidak memiliki data NIM. Dilewati.");
                $invalidCount++;
                continue;
            }

            // Split NIM yang dipisahkan titik koma, koma, spasi
            $nimsList = preg_split('/[;, \t\r\n]+/', $rawNims);
            $nimsList = array_filter(array_map('trim', $nimsList), fn($v) => $v !== '');

            $validNims = [];
            foreach ($nimsList as $n) {
                try {
                    $normalized = Document::normalizeNim($n);
                    $isVerified = isset($verifiedNims[$normalized]);

                    if ($isVerified) {
                        $validNims[] = $normalized;
                    } elseif ($allowUnknown) {
                        $this->warn("Baris " . ($i + 1) . " (ID {$rawId}): NIM '{$normalized}' tidak dikenal, tetap diimpor (--allow-unknown aktif).");
                        $validNims[] = $normalized;
                    } else {
                        $this->warn("Baris " . ($i + 1) . " (ID {$rawId}): NIM '{$normalized}' tidak terdaftar pada data mahasiswa. Dilewati (gunakan --allow-unknown).");
                    }
                } catch (\Throwable $e) {
                    $this->error("Baris " . ($i + 1) . " (ID {$rawId}): " . $e->getMessage());
                }
            }

            $validNims = array_values(array_unique($validNims));

            if (empty($validNims)) {
                $invalidCount++;
                continue;
            }

            if (! $dryRun) {
                // Tambahkan NIM tanpa menghapus pivot yang sudah ada sebelumnya
                $existing = $doc->nims()->pluck('nim')->toArray();
                $toAdd = array_values(array_diff($validNims, $existing));
                if (!empty($toAdd)) {
                    $records = array_map(fn($nim) => [
                        'document_id' => $doc->id,
                        'nim'         => $nim,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ], $toAdd);
                    DocumentNim::insert($records);
                }
            }

            $successCount++;
            $totalNimsLinked += count($validNims);
            $this->line("✓ Dokumen ID {$doc->id} ({$doc->title}): " . count($validNims) . " NIM " . ($dryRun ? '[SIMULASI]' : 'berhasil ditautkan'));
        }

        $this->newLine();
        $this->info("=== Ringkasan Import Review NIM ===");
        $this->info("Dokumen berhasil diproses : {$successCount}");
        $this->info("Total NIM ditautkan       : {$totalNimsLinked}");
        $this->info("Baris tidak valid/dilewati : {$invalidCount}");

        if (! $dryRun && $successCount > 0) {
            activity('document_nim_import')
                ->withProperties([
                    'action'            => 'import_nim_review',
                    'file'              => $filePath,
                    'documents_count'   => $successCount,
                    'total_nims_linked' => $totalNimsLinked,
                    'invalid_rows'      => $invalidCount,
                    'allow_unknown'     => $allowUnknown,
                    'dry_run'           => false,
                ])
                ->log("Import review NIM: {$successCount} dokumen ({$totalNimsLinked} NIM) berhasil diproses, {$invalidCount} dilewati");
        }

        return $invalidCount > 0 && $successCount === 0 ? 1 : 0;
    }
}
