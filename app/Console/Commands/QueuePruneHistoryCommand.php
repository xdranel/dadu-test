<?php

namespace App\Console\Commands;

use App\Models\QueueTicketEvent;
use App\Models\QueueTicketHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QueuePruneHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:prune-history 
                            {--dry-run : Simulasi pengecekan tanpa menghapus data apapun}
                            {--force : Lewati konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune data arsip antrian dan event tiket yang lebih tua dari batas retensi (default 24 bulan) dengan pengaman file export';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionMonths = (int) config('queue.history_retention_months', 24);
        if ($retentionMonths < 1) {
            $retentionMonths = 24;
        }

        $now = now('Asia/Jakarta');
        // Batas bulan penuh: data yang berumur >= N bulan (bulan penuh sebelum batas retensi)
        $cutoffDate = $now->copy()->subMonths($retentionMonths)->startOfMonth()->toDateString();

        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("=== PEMERIKSAAN RETENSI RIWAYAT ANTRIAN ({$retentionMonths} BULAN) ===");
        $this->info("Waktu acuan (WIB) : " . $now->format('Y-m-d H:i:s'));
        $this->info("Batas cutoff retensi : Tanggal sebelum {$cutoffDate}");

        if ($isDryRun) {
            $this->warn("[MODE DRY-RUN] Data tidak akan dihapus.");
        }

        // Cari bulan-bulan yang memiliki data lebih tua dari batas cutoff
        $oldestDate = DB::table('queue_ticket_histories')
            ->where('queue_date', '<', $cutoffDate)
            ->min('queue_date');

        if (!$oldestDate) {
            $this->info("Tidak ditemukan data riwayat antrian yang melebihi batas retensi ({$retentionMonths} bulan).");
            return Command::SUCCESS;
        }

        // Dapatkan daftar bulan yang eligible untuk diprune (format YYYY-MM)
        $eligibleMonths = DB::table('queue_ticket_histories')
            ->where('queue_date', '<', $cutoffDate)
            ->select(DB::raw("DISTINCT SUBSTRING(queue_date, 1, 7) as month_str"))
            ->orderBy('month_str', 'asc')
            ->pluck('month_str')
            ->all();

        if (empty($eligibleMonths)) {
            $this->info("Tidak ada bulan yang memenuhi kriteria retensi.");
            return Command::SUCCESS;
        }

        $exportDir = storage_path('app/queue-exports');
        $validatedMonths = [];
        $rejectedMonths = [];

        foreach ($eligibleMonths as $month) {
            $monthStart = Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->startOfMonth()->toDateString();
            $monthEnd = Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();

            $dbHistoryCount = DB::table('queue_ticket_histories')
                ->where('queue_date', '>=', $monthStart)
                ->where('queue_date', '<=', $monthEnd)
                ->count();

            $dbEventCount = DB::table('queue_ticket_events')
                ->where('queue_date', '>=', $monthStart)
                ->where('queue_date', '<=', $monthEnd)
                ->count();

            $exportFile = $exportDir . DIRECTORY_SEPARATOR . "queue-{$month}.csv";

            // PENGAMAN 1: Cek keberadaan file export
            if (!File::exists($exportFile)) {
                $rejectedMonths[$month] = "File export 'queue-{$month}.csv' TIDAK DITEMUKAN di storage/app/queue-exports/";
                continue;
            }

            // PENGAMAN 2: Cek file tidak boleh 0 byte (kosong)
            if (File::size($exportFile) === 0) {
                $rejectedMonths[$month] = "File export 'queue-{$month}.csv' KOSONG (0 byte)";
                continue;
            }

            // PENGAMAN 3: Cek jumlah baris data di file CSV (toleransi nol)
            $csvDataRows = $this->countCsvDataRows($exportFile);
            if ($csvDataRows !== $dbHistoryCount) {
                $rejectedMonths[$month] = "Jumlah baris data pada file CSV ({$csvDataRows}) TIDAK COCOK dengan jumlah record di database ({$dbHistoryCount})";
                continue;
            }

            $validatedMonths[$month] = [
                'history_count' => $dbHistoryCount,
                'event_count' => $dbEventCount,
                'start_date' => $monthStart,
                'end_date' => $monthEnd,
                'export_file' => "queue-{$month}.csv",
            ];
        }

        // Tampilkan laporan bulan yang ditolak
        if (!empty($rejectedMonths)) {
            $this->newLine();
            $this->error("PERINGATAN PENGAMAN: Bulan berikut DITOLAK untuk diprune:");
            foreach ($rejectedMonths as $m => $reason) {
                $this->line(" - [{$m}]: {$reason}");
                Log::warning("Pengaman prune riwayat antrian menolak bulan {$m}: {$reason}");
            }
        }

        // Jika tidak ada bulan yang lolos verifikasi pengaman
        if (empty($validatedMonths)) {
            $this->newLine();
            $this->warn("Tidak ada bulan yang lolos verifikasi pengaman untuk dihapus.");
            return Command::FAILURE;
        }

        // Tampilkan bulan yang siap diprune
        $this->newLine();
        $this->info("Bulan yang LOLOS verifikasi pengaman dan siap diprune:");
        $totalHistoryToDelete = 0;
        $totalEventsToDelete = 0;

        $tableRows = [];
        foreach ($validatedMonths as $m => $info) {
            $tableRows[] = [
                $m,
                $info['history_count'],
                $info['event_count'],
                $info['export_file'] . ' (Valid)',
            ];
            $totalHistoryToDelete += $info['history_count'];
            $totalEventsToDelete += $info['event_count'];
        }
        $this->table(['Bulan', 'Tiket Histories', 'Ticket Events', 'Status File Export'], $tableRows);
        $this->info("Total data: {$totalHistoryToDelete} baris tiket, {$totalEventsToDelete} baris event.");

        if ($isDryRun) {
            $this->newLine();
            $this->info("[DRY-RUN SELESAI] Tidak ada data yang dihapus dari database.");
            return Command::SUCCESS;
        }

        // Konfirmasi interaktif jika bukan --force
        if (!$force) {
            $confirm = $this->confirm("Apakah Anda yakin ingin MENGHAPUS PERMANEN data untuk " . count($validatedMonths) . " bulan di atas?", false);
            if (!$confirm) {
                $this->warn("Operasi prune dibatalkan oleh pengguna.");
                return Command::SUCCESS;
            }
        }

        // Eksekusi penghapusan bertahap (batch per 1.000 baris) di dalam transaksi
        $batchSize = 1000;
        $deletedHistoriesTotal = 0;
        $deletedEventsTotal = 0;

        foreach ($validatedMonths as $m => $info) {
            $this->info("Menghapus data bulan {$m}...");
            $mStart = $info['start_date'];
            $mEnd = $info['end_date'];

            // 1. Hapus queue_ticket_histories per batch
            do {
                $deletedHistoryBatch = DB::transaction(function () use ($mStart, $mEnd, $batchSize) {
                    $ids = DB::table('queue_ticket_histories')
                        ->where('queue_date', '>=', $mStart)
                        ->where('queue_date', '<=', $mEnd)
                        ->limit($batchSize)
                        ->pluck('id');

                    if ($ids->isNotEmpty()) {
                        return DB::table('queue_ticket_histories')->whereIn('id', $ids)->delete();
                    }
                    return 0;
                });
                $deletedHistoriesTotal += $deletedHistoryBatch;
            } while ($deletedHistoryBatch > 0);

            // 2. Hapus queue_ticket_events per batch
            do {
                $deletedEventBatch = DB::transaction(function () use ($mStart, $mEnd, $batchSize) {
                    $ids = DB::table('queue_ticket_events')
                        ->where('queue_date', '>=', $mStart)
                        ->where('queue_date', '<=', $mEnd)
                        ->limit($batchSize)
                        ->pluck('id');

                    if ($ids->isNotEmpty()) {
                        return DB::table('queue_ticket_events')->whereIn('id', $ids)->delete();
                    }
                    return 0;
                });
                $deletedEventsTotal += $deletedEventBatch;
            } while ($deletedEventBatch > 0);

            Log::info("Prune riwayat antrian selesai untuk bulan {$m}", [
                'month' => $m,
                'deleted_histories' => $info['history_count'],
                'deleted_events' => $info['event_count'],
            ]);
        }

        $this->newLine();
        $this->info("SUKSES: Selesai memprune data ({$deletedHistoriesTotal} tiket, {$deletedEventsTotal} event).");
        return Command::SUCCESS;
    }

    /**
     * Hitung jumlah baris data di file CSV (mengabaikan baris header pertama).
     */
    private function countCsvDataRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return -1;
        }

        $rows = 0;
        $isFirst = true;

        while (($data = fgetcsv($handle)) !== false) {
            // Lewati baris header pertama
            if ($isFirst) {
                $isFirst = false;
                continue;
            }
            // Abaikan baris kosong semata
            if ($data === [null] || empty(array_filter($data, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }
            $rows++;
        }

        fclose($handle);
        return $rows;
    }
}
