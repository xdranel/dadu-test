<?php

namespace App\Console\Commands;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class BackfillNimCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:backfill-nim
                            {--dry-run : Menjalankan simulasi tanpa mengubah database}
                            {--include-multi : Mengisi juga dokumen yang memiliki 2 atau lebih NIM terverifikasi}
                            {--limit= : Batasi jumlah dokumen yang diproses}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill relasi NIM dokumen kategori mahasiswa ke tabel pivot document_nim';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeMulti = (bool) $this->option('include-multi');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info($dryRun
            ? '=== Memulai Backfill NIM Dokumen (MODE DRY-RUN) ==='
            : '=== Memulai Backfill NIM Dokumen ==='
        );

        // Validasi ketersediaan tabel users dan role mahasiswa
        if (! Schema::hasTable('users') || ! Role::whereIn('name', ['mahasiswa', 'Mahasiswa'])->exists()) {
            $this->error('Tabel users atau role mahasiswa tidak tersedia untuk verifikasi NIM. Proses dihentikan.');
            return 1;
        }

        // Ambil daftar NIM mahasiswa valid dari tabel users
        $verifiedNims = User::whereHas('roles', fn($q) => $q->whereIn('name', ['mahasiswa', 'Mahasiswa']))
            ->whereNotNull('employee_id')
            ->where('employee_id', '!=', '')
            ->pluck('employee_id')
            ->map(fn($v) => (string) $v)
            ->flip()
            ->toArray();

        $query = Document::whereDoesntHave('nims')
            ->where(function ($q) {
                $q->where('access_group', AccessGroup::MAHASISWA->value)
                  ->orWhereNotNull('owner_nim');
            });

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $totalCandidate = $query->count();
        $this->info("Ditemukan {$totalCandidate} dokumen target tanpa relasi NIM.");

        if ($totalCandidate === 0) {
            $this->info('Tidak ada dokumen yang perlu dibackfill.');
            return 0;
        }

        $countSingleFilled = 0;
        $countMultiFilled = 0;
        $countMultiPending = 0;
        $countZero = 0;
        $countUnknown = 0;

        $reviewCsvRows = [];
        $reviewCsvRows[] = ['id', 'judul', 'nama_berkas', 'nim_terdeteksi', 'kelompok', 'status_verifikasi'];

        $documents = $limit && $limit > 0 ? $query->get() : $query->lazyById(100);

        foreach ($documents as $doc) {
            $candidates = $this->extractNims($doc);

            $verified = [];
            $unverified = [];
            $statusPerNim = [];

            foreach ($candidates as $cand) {
                if (isset($verifiedNims[$cand])) {
                    $verified[] = $cand;
                    $statusPerNim[] = "{$cand}:TERVERIFIKASI";
                } else {
                    $unverified[] = $cand;
                    $statusPerNim[] = "{$cand}:TIDAK_DIKENAL";
                }
            }

            $statusString = !empty($statusPerNim) ? implode('; ', $statusPerNim) : 'TIDAK_ADA_KANDIDAT';
            $detectedString = !empty($candidates) ? implode(';', $candidates) : '-';

            if (!empty($unverified)) {
                // Kelompok (d): Memiliki kandidat tidak dikenal -> hanya masuk CSV review
                $countUnknown++;
                $reviewCsvRows[] = [
                    $doc->id,
                    $doc->title,
                    $doc->file_name,
                    $detectedString,
                    'kandidat_tidak_dikenal',
                    $statusString,
                ];
                $this->warn("? Dokumen ID {$doc->id}: memiliki kandidat tidak dikenal ({$statusString}) dicatat ke CSV review");
            } elseif (count($verified) === 1) {
                // Kelompok (a): Tepat 1 kandidat terverifikasi -> langsung diisi
                if (! $dryRun) {
                    $doc->syncNims($verified);
                }
                $countSingleFilled++;
                $this->line("✓ Dokumen ID {$doc->id}: tepat 1 NIM terverifikasi ({$verified[0]}) " . ($dryRun ? '[SIMULASI]' : 'diisi'));
            } elseif (count($verified) > 1) {
                // Kelompok (b): 2+ kandidat terverifikasi
                if ($includeMulti) {
                    if (! $dryRun) {
                        $doc->syncNims($verified);
                    }
                    $countMultiFilled++;
                    $this->line("✓ Dokumen ID {$doc->id}: multi NIM terverifikasi ({$detectedString}) diisi (--include-multi aktif)");
                } else {
                    $countMultiPending++;
                    $reviewCsvRows[] = [
                        $doc->id,
                        $doc->title,
                        $doc->file_name,
                        $detectedString,
                        'multi_terverifikasi',
                        $statusString,
                    ];
                    $this->warn("! Dokumen ID {$doc->id}: multi NIM terverifikasi ({$detectedString}) dicatat ke CSV review");
                }
            } else {
                // Kelompok (c): 0 kandidat terverifikasi
                $countZero++;
                $reviewCsvRows[] = [
                    $doc->id,
                    $doc->title,
                    $doc->file_name,
                    '-',
                    'tanpa_nim',
                    'Tidak terdeteksi kandidat NIM',
                ];
                $this->line("- Dokumen ID {$doc->id}: 0 NIM dicatat ke CSV review");
            }
        }

        // Tulis CSV review jika terdapat kelompok b, c, atau d
        $csvPath = null;
        if (count($reviewCsvRows) > 1) {
            $csvDir = 'reports';
            if (! Storage::disk('private')->exists($csvDir)) {
                Storage::disk('private')->makeDirectory($csvDir);
            }
            $csvFilename = 'review_nim_' . now()->format('Ymd_His') . ($dryRun ? '_dryrun' : '') . '.csv';
            $csvPath = "{$csvDir}/{$csvFilename}";

            $fp = fopen('php://temp', 'r+');
            foreach ($reviewCsvRows as $row) {
                fputcsv($fp, $row);
            }
            rewind($fp);
            $csvContent = stream_get_contents($fp);
            fclose($fp);

            Storage::disk('private')->put($csvPath, $csvContent);
            $this->newLine();
            $this->info("Laporan CSV review disimpan di storage private: {$csvPath}");
        }

        $this->newLine();
        $this->info("=== Ringkasan Backfill NIM ===");
        $this->info("Kelompok (a) Single NIM (langsung diisi) : {$countSingleFilled}");
        $this->info("Kelompok (b) Multi NIM (diisi)          : {$countMultiFilled}");
        $this->info("Kelompok (b) Multi NIM (pending review) : {$countMultiPending}");
        $this->info("Kelompok (c) Tanpa NIM (0 NIM)          : {$countZero}");
        $this->info("Kelompok (d) Kandidat Tidak Dikenal     : {$countUnknown}");

        if (! $dryRun) {
            activity('document_nim_backfill')
                ->withProperties([
                    'action'          => 'backfill_nim',
                    'single_filled'   => $countSingleFilled,
                    'multi_filled'    => $countMultiFilled,
                    'multi_pending'   => $countMultiPending,
                    'zero_nim'        => $countZero,
                    'unknown_nim'     => $countUnknown,
                    'dry_run'         => false,
                    'include_multi'   => $includeMulti,
                    'csv_report'      => $csvPath,
                ])
                ->log("Backfill NIM dokumen: {$countSingleFilled} single diisi, {$countMultiFilled} multi diisi, {$countMultiPending} pending, {$countZero} tanpa NIM, {$countUnknown} tidak dikenal");
        }

        return 0;
    }

    /**
     * Ekstrak seluruh kandidat NIM digit unik yang berdiri sendiri (non-digit boundary)
     * berdasarkan rentang panjang config documents.nim_min_length dan nim_max_length.
     */
    protected function extractNims(Document $doc): array
    {
        $min = (int) config('documents.nim_min_length', 8);
        $max = (int) config('documents.nim_max_length', 15);

        $texts = [
            $doc->owner_nim ?? '',
            $doc->file_name ?? '',
            $doc->original_file_name ?? '',
            $doc->title ?? '',
            $doc->keywords ?? '',
        ];
        $combined = implode(' ', $texts);

        // Boundary regex: batas non-digit di kedua sisi agar angka lebih panjang dari max tidak terpotong
        $pattern = '/(?<!\d)(\d{' . $min . ',' . $max . '})(?!\d)/';
        preg_match_all($pattern, $combined, $matches);

        $nims = array_unique($matches[1] ?? []);
        return array_values($nims);
    }
}
