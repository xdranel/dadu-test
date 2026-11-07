<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\QueueTicketHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueHistoryController extends Controller
{
    /**
     * Halaman daftar riwayat antrian (admin saja)
     */
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);
        $perPage = (int) ($filters['per_page'] ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $combinedQuery = $this->buildCombinedQuery($filters);

        // Ringkasan metrik untuk filter aktif
        $summary = $this->calculateSummary(clone $combinedQuery);

        // Pengurutan dan pagination
        $tickets = $combinedQuery
            ->orderBy('queue_date', 'desc')
            ->orderBy('code', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Tanggal data tertua yang masih tersimpan
        $oldestDate = DB::table('queue_ticket_histories')->min('queue_date')
            ?: DB::table('queue_tickets')->min('queue_date');

        // Master data untuk dropdown filter
        $counters = QueueCounter::orderBy('id')->get();
        $categories = config('queue.categories', []);
        $studyPrograms = config('queue.study_programs', []);
        $officers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'Petugas', 'Akademik']);
        })->orderBy('name')->get();

        return view('queue-tickets.history', compact(
            'tickets',
            'summary',
            'filters',
            'oldestDate',
            'counters',
            'categories',
            'studyPrograms',
            'officers',
            'perPage'
        ));
    }

    /**
     * Halaman detail satu tiket dialihkan kembali ke daftar riwayat (detail page dinonaktifkan)
     */
    public function show(string $id, Request $request)
    {
        return redirect()->route('admin.queue.history');
    }

    /**
     * Export data riwayat antrian ke CSV berdasarkan filter yang aktif
     */
    public function exportCsv(Request $request)
    {
        $filters = $this->validateFilters($request);
        $combinedQuery = $this->buildCombinedQuery($filters)
            ->orderBy('queue_date', 'asc')
            ->orderBy('code', 'asc');

        $user = $request->user();
        $filename = 'riwayat-antrian-' . now('Asia/Jakarta')->format('Ymd-His') . '.csv';

        // Cache counter & user untuk efisiensi
        $counters = QueueCounter::pluck('name', 'id')->all();
        $users = User::pluck('name', 'id')->all();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($combinedQuery, $counters, $users, $user, $filters) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM untuk kompatibilitas Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Header baris CSV
            fputcsv($handle, [
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
            ]);

            $rowCount = 0;

            foreach ($combinedQuery->cursor() as $row) {
                $rowCount++;

                $statusLabel = match ($row->status) {
                    'finished' => 'Selesai',
                    'skipped' => 'Dilewati',
                    'waiting' => 'Menunggu',
                    'called' => 'Dipanggil',
                    'unserved' => 'Tidak Terlayani',
                    default => ucfirst($row->status),
                };

                fputcsv($handle, [
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
                ]);
            }

            fclose($handle);

            Log::info("Admin mengekspor riwayat antrian ke CSV", [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'filters' => $filters,
                'rows_count' => $rowCount,
            ]);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Unduh file export arsip bulanan dari storage/app/queue-exports/
     */
    public function downloadExport(string $filename)
    {
        if (!preg_match('/^queue-\d{4}-\d{2}\.csv$/', $filename)) {
            abort(404, 'Format nama file export tidak valid.');
        }

        $exportDir = storage_path('app/queue-exports');
        $filePath = $exportDir . DIRECTORY_SEPARATOR . $filename;

        if (!File::exists($filePath)) {
            abort(404, 'File export arsip tidak ditemukan.');
        }

        // Cegah path traversal
        $realDir = realpath($exportDir);
        $realPath = realpath($filePath);
        if (!$realPath || !str_starts_with($realPath, $realDir)) {
            abort(403, 'Akses tidak sah.');
        }

        Log::info("Admin mengunduh file export arsip bulanan", [
            'user_id' => auth()->id(),
            'file' => $filename,
        ]);

        return response()->download($filePath, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Validasi input filter request
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'nim' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:100'],
            'service_category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:finished,skipped,unserved,called,waiting'],
            'counter_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        // Default bulan ini jika tidak ada filter tanggal / NIM
        if (empty($validated['nim']) && empty($validated['start_date']) && empty($validated['month'])) {
            $validated['month'] = now('Asia/Jakarta')->format('Y-m');
        }

        return $validated;
    }

    /**
     * Membangun subquery UNION ALL untuk queue_tickets dan queue_ticket_histories
     */
    private function buildCombinedQuery(array $filters)
    {
        $ticketQuery = DB::table('queue_tickets')
            ->select([
                'id',
                'code',
                'nim',
                'name',
                'study_program',
                'service_category',
                'notes',
                'queue_date',
                'status',
                'counter_id',
                'called_by',
                'called_at',
                'finished_at',
                'skipped_at',
                'skipped_by',
                'created_at',
                'updated_at',
                DB::raw("'current' as source_type"),
            ]);

        $historyQuery = DB::table('queue_ticket_histories')
            ->select([
                'id',
                'code',
                'nim',
                'name',
                'study_program',
                'service_category',
                'notes',
                'queue_date',
                'status',
                'counter_id',
                'called_by',
                'called_at',
                'finished_at',
                'skipped_at',
                'skipped_by',
                'created_at',
                'updated_at',
                DB::raw("'history' as source_type"),
            ]);

        // Terapkan filter pada masing-masing subkueri
        $this->applyFilters($ticketQuery, $filters);
        $this->applyFilters($historyQuery, $filters);

        // Gabungkan dengan UNION ALL dalam query wrapper
        $union = $ticketQuery->unionAll($historyQuery);
        return DB::query()->fromSub($union, 't');
    }

    /**
     * Terapkan filter pada query builder
     */
    private function applyFilters($query, array $filters): void
    {
        // 1. Filter NIM: Jika diisi, cari sepanjang waktu (lintas bulan) dengan prefix matching
        if (!empty($filters['nim'])) {
            $nim = trim($filters['nim']);
            $query->where('nim', 'like', $nim . '%');
        } else {
            // Filter Tanggal / Bulan hanya aktif jika NIM tidak sedang dicari
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $query->where('queue_date', '>=', $filters['start_date'])
                    ->where('queue_date', '<=', $filters['end_date']);
            } elseif (!empty($filters['start_date'])) {
                $query->where('queue_date', '>=', $filters['start_date']);
            } elseif (!empty($filters['end_date'])) {
                $query->where('queue_date', '<=', $filters['end_date']);
            } elseif (!empty($filters['month'])) {
                // Rentang awal bulan sampai akhir bulan tanpa whereMonth/whereDate
                $mStart = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta')->startOfMonth()->toDateString();
                $mEnd = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta')->endOfMonth()->toDateString();
                $query->where('queue_date', '>=', $mStart)
                    ->where('queue_date', '<=', $mEnd);
            }
        }

        // 2. Filter Nama: Mengandung kata
        if (!empty($filters['name'])) {
            $name = trim($filters['name']);
            $query->where('name', 'like', '%' . $name . '%');
        }

        // 3. Filter Layanan
        if (!empty($filters['service_category'])) {
            $query->where('service_category', $filters['service_category']);
        }

        // 4. Filter Status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 5. Filter Loket
        if (!empty($filters['counter_id'])) {
            $query->where('counter_id', (int) $filters['counter_id']);
        }

        // 6. Filter Petugas
        if (!empty($filters['user_id'])) {
            $uid = (int) $filters['user_id'];
            $query->where(function ($sub) use ($uid) {
                $sub->where('called_by', $uid)
                    ->orWhere('skipped_by', $uid);
            });
        }
    }

    /**
     * Hitung ringkasan metrik untuk filter aktif
     */
    private function calculateSummary($combinedQuery): array
    {
        $raw = (clone $combinedQuery)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('finished', 'skipped') THEN 1 ELSE 0 END) as finished_count,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
                SUM(CASE WHEN status = 'unserved' THEN 1 ELSE 0 END) as unserved_count
            ")
            ->first();

        return [
            'total' => (int) ($raw->total ?? 0),
            'finished' => (int) ($raw->finished_count ?? 0),
            'skipped' => (int) ($raw->skipped_count ?? 0),
            'unserved' => (int) ($raw->unserved_count ?? 0),
        ];
    }

    /**
     * Sanitasi sel teks pencegah CSV Formula Injection
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
