<?php

namespace App\Console\Commands;

use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\QueueTicketHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QueueExportMonthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:export-month {bulan? : Format YYYY-MM (default: bulan sebelumnya)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export data riwayat antrian bulanan ke storage/app/queue-exports/queue-YYYY-MM.csv';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthArg = $this->argument('bulan');

        if ($monthArg) {
            if (!preg_match('/^\d{4}-\d{2}$/', $monthArg)) {
                $this->error("Format bulan tidak valid. Harap gunakan format YYYY-MM (contoh: 2026-09).");
                return Command::FAILURE;
            }
            $targetMonth = $monthArg;
        } else {
            // Default: bulan sebelumnya (dalam Asia/Jakarta)
            $targetMonth = now('Asia/Jakarta')->subMonth()->format('Y-m');
        }

        $startDate = Carbon::createFromFormat('Y-m', $targetMonth, 'Asia/Jakarta')->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $targetMonth, 'Asia/Jakarta')->endOfMonth()->toDateString();

        $this->info("Menyiapkan export antrian untuk periode: {$startDate} s/d {$endDate} ({$targetMonth})...");

        $exportDir = storage_path('app/queue-exports');
        if (!File::isDirectory($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $filePath = $exportDir . DIRECTORY_SEPARATOR . "queue-{$targetMonth}.csv";
        $isOverwriting = File::exists($filePath);

        if ($isOverwriting) {
            Log::info("Menimpa file export antrian yang sudah ada: queue-{$targetMonth}.csv");
            $this->warn("File queue-{$targetMonth}.csv sudah ada, akan ditimpa.");
        }

        // Cache nama counter dan user untuk menghindari N+1 kueri
        $counters = QueueCounter::pluck('name', 'id')->all();
        $users = User::pluck('name', 'id')->all();

        // Kueri dari queue_ticket_histories
        $historyQuery = DB::table('queue_ticket_histories')
            ->where('queue_date', '>=', $startDate)
            ->where('queue_date', '<=', $endDate);

        // Jika bulan ini, sertakan juga queue_tickets yang belum terarsip
        $currentMonth = now('Asia/Jakarta')->format('Y-m');
        if ($targetMonth === $currentMonth) {
            $ticketQuery = DB::table('queue_tickets')
                ->where('queue_date', '>=', $startDate)
                ->where('queue_date', '<=', $endDate);

            $unionQuery = $ticketQuery->unionAll($historyQuery);
            $finalQuery = DB::query()->fromSub($unionQuery, 'combined')
                ->orderBy('queue_date', 'asc')
                ->orderBy('code', 'asc');
        } else {
            $finalQuery = $historyQuery
                ->orderBy('queue_date', 'asc')
                ->orderBy('code', 'asc');
        }

        $handle = fopen($filePath, 'w');
        if (!$handle) {
            $this->error("Gagal membuka file tujuan: {$filePath}");
            return Command::FAILURE;
        }

        // Tulis UTF-8 BOM agar rapi saat dibuka di Microsoft Excel
        fwrite($handle, "\xEF\xBB\xBF");

        // Header CSV
        $headers = [
            'Tanggal',
            'Nomor Antrian',
            'NIM',
            'Nama Mahasiswa',
            'Program Studi',
            'Kategori Layanan',
            'Catatan',
            'Status',
            'Loket',
            'Petugas Panggil',
            'Petugas Lewati',
            'Jam Ambil',
            'Jam Dipanggil',
            'Jam Selesai',
            'Jam Dilewati',
            'Riwayat Kejadian',
        ];
        fputcsv($handle, $headers);

        $rowCount = 0;

        foreach ($finalQuery->cursor() as $row) {
            $rowCount++;

            // Ringkasan timeline kejadian
            $events = QueueTicketEvent::where('queue_date', $row->queue_date)
                ->where('number', (int) $row->code)
                ->orderBy('created_at', 'asc')
                ->get();

            $eventSummaries = [];
            foreach ($events as $ev) {
                $time = $ev->created_at ? $ev->created_at->timezone('Asia/Jakarta')->format('H:i:s') : '-';
                $cName = $ev->counter_id ? ($counters[$ev->counter_id] ?? "Loket {$ev->counter_id}") : null;
                $uName = $ev->user_id ? ($users[$ev->user_id] ?? "User {$ev->user_id}") : null;

                $extra = array_filter([$cName, $uName]);
                $extraStr = !empty($extra) ? ' (' . implode(', ', $extra) . ')' : '';
                $eventSummaries[] = "{$ev->event}: {$time}{$extraStr}";
            }
            $eventTimelineStr = !empty($eventSummaries) ? implode(' | ', $eventSummaries) : '-';

            $statusLabel = match ($row->status) {
                'finished' => 'Selesai',
                'skipped' => 'Dilewati',
                'waiting' => 'Menunggu',
                'called' => 'Dipanggil',
                'unserved' => 'Tidak Terlayani',
                default => ucfirst($row->status),
            };

            $csvRow = [
                $this->sanitizeCsv($row->queue_date),
                $this->sanitizeCsv($row->code),
                $this->sanitizeCsv($row->nim ?? '-'),
                $this->sanitizeCsv($row->name ?? '-'),
                $this->sanitizeCsv($row->study_program ?? '-'),
                $this->sanitizeCsv($row->service_category ?? '-'),
                $this->sanitizeCsv($row->notes ?? '-'),
                $this->sanitizeCsv($statusLabel),
                $this->sanitizeCsv($row->counter_id ? ($counters[$row->counter_id] ?? "Loket {$row->counter_id}") : '-'),
                $this->sanitizeCsv($row->called_by ? ($users[$row->called_by] ?? "User {$row->called_by}") : '-'),
                $this->sanitizeCsv(isset($row->skipped_by) && $row->skipped_by ? ($users[$row->skipped_by] ?? "User {$row->skipped_by}") : '-'),
                $this->sanitizeCsv($row->created_at ? Carbon::parse($row->created_at)->timezone('Asia/Jakarta')->format('H:i:s') : '-'),
                $this->sanitizeCsv($row->called_at ? Carbon::parse($row->called_at)->timezone('Asia/Jakarta')->format('H:i:s') : '-'),
                $this->sanitizeCsv($row->finished_at ? Carbon::parse($row->finished_at)->timezone('Asia/Jakarta')->format('H:i:s') : '-'),
                $this->sanitizeCsv(isset($row->skipped_at) && $row->skipped_at ? Carbon::parse($row->skipped_at)->timezone('Asia/Jakarta')->format('H:i:s') : '-'),
                $this->sanitizeCsv($eventTimelineStr),
            ];

            fputcsv($handle, $csvRow);
        }

        fclose($handle);

        Log::info("Export antrian bulanan selesai", [
            'trigger' => 'artisan queue:export-month',
            'month' => $targetMonth,
            'file' => "queue-{$targetMonth}.csv",
            'path' => $filePath,
            'rows_count' => $rowCount,
        ]);

        $this->info("Export berhasil! File disimpan di: {$filePath} ({$rowCount} baris).");
        return Command::SUCCESS;
    }

    /**
     * Sanitasi teks pencegah CSV Formula Injection.
     * Jika diawali =, +, -, @, tab (\t), atau carriage return (\r), bubuhkan tanda petik tunggal (').
     */
    private function sanitizeCsv(?string $val): string
    {
        if ($val === null) {
            return '';
        }
        $val = (string) $val;
        if (strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $val;
        }
        return $val;
    }
}
