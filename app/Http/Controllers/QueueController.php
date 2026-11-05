<?php

namespace App\Http\Controllers;

use App\Models\QueueCounter;
use App\Models\QueueNumberCounter;
use App\Models\QueueTicket;
use App\Models\QueueState;
use App\Support\QueueSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class QueueController extends Controller
{
    /**
     * Tampilkan form ambil antrian jika layanan buka,
     * redirect jika sudah punya tiket aktif,
     * atau tampilkan halaman closed jika di luar jam operasional.
     */
    public function take(Request $request)
    {
        $deviceToken = $request->cookie('device_token');

        // Jika perangkat sudah memiliki tiket aktif, arahkan langsung ke halaman status
        if ($deviceToken && Str::isUuid($deviceToken)) {
            $existing = QueueTicket::where('device_token', $deviceToken)
                ->whereIn('status', ['waiting', 'called'])
                ->first();

            if ($existing) {
                return redirect()->route('queue.status');
            }
        }

        // Cek jam operasional pengambilan tiket
        if (!QueueSchedule::isOpen()) {
            return response()->view('queue-tickets.closed', [
                'nextOpening' => QueueSchedule::nextOpening(),
                'scheduleSummary' => QueueSchedule::getScheduleSummary(),
            ]);
        }

        $studyPrograms = config('queue.study_programs', []);
        $categories = config('queue.categories', []);

        return view('queue-tickets.take', compact('studyPrograms', 'categories'));
    }

    /**
     * Simpan pengajuan tiket antrian baru.
     */
    public function store(Request $request)
    {
        // 1. Cek jam operasional
        if (!QueueSchedule::isOpen()) {
            return redirect()->route('queue.take')->with('error', 'Layanan saat ini sedang tutup.');
        }

        // 2. Ambil parameter validasi dari config
        $minNim = config('queue.nim_min_length', 8);
        $maxNim = config('queue.nim_max_length', 15);
        $studyPrograms = config('queue.study_programs', []);
        $categories = config('queue.categories', []);

        // 3. Validasi input form
        $validated = $request->validate([
            // NIM: hanya angka, panjang dapat dikonfigurasi di config/queue.php
            'nim' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                "min:{$minNim}",
                "max:{$maxNim}",
            ],
            // Nama: alfabet, spasi, titik, apostrof, tanda hubung (3-100 karakter)
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[\pL\s\.\'\-]+$/u',
            ],
            // Program Studi: wajib sesuai daftar di config
            'study_program' => [
                'required',
                'string',
                Rule::in($studyPrograms),
            ],
            // Layanan: wajib sesuai daftar di config
            'service_category' => [
                'required',
                'string',
                Rule::in($categories),
            ],
            // Catatan: opsional, maksimal 255 karakter
            'notes' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'nim.required' => 'NIM wajib diisi.',
            'nim.regex' => 'NIM hanya boleh berisi angka.',
            'nim.min' => "NIM minimal terdiri dari {$minNim} digit angka.",
            'nim.max' => "NIM maksimal terdiri dari {$maxNim} digit angka.",
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama minimal terdiri dari 3 karakter.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'name.regex' => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'study_program.required' => 'Program studi wajib dipilih.',
            'study_program.in' => 'Program studi yang dipilih tidak valid.',
            'service_category.required' => 'Kategori layanan wajib dipilih.',
            'service_category.in' => 'Kategori layanan yang dipilih tidak valid.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
        ]);

        // Dapatkan token perangkat
        $deviceToken = $request->cookie('device_token');
        if (!$deviceToken || !Str::isUuid($deviceToken)) {
            $deviceToken = (string) Str::uuid();
        }

        $today = now('Asia/Jakarta')->toDateString();
        $isSecure = $request->isSecure();

        // 4. Buat tiket di dalam transaksi dengan retry jika terjadi duplicate collision
        $ticket = null;
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $ticket = DB::transaction(function () use ($deviceToken, $validated, $today) {
                    // Cek tiket aktif perangkat di dalam transaksi dengan lockForUpdate
                    $existingInTx = QueueTicket::where('device_token', $deviceToken)
                        ->whereIn('status', ['waiting', 'called'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingInTx) {
                        return $existingInTx;
                    }

                    // Kunci baris counter hari ini untuk mencegah race condition
                    // Menggunakan QueueNumberCounter jika tabel queue_number_counters tersedia, atau fallback
                    $counterModel = class_exists(QueueNumberCounter::class) && \Illuminate\Support\Facades\Schema::hasTable('queue_number_counters')
                        ? QueueNumberCounter::class
                        : QueueCounter::class;

                    $counter = $counterModel::where('queue_date', $today)->lockForUpdate()->first();
                    if (!$counter) {
                        try {
                            $counterModel::create([
                                'queue_date' => $today,
                                'last_number' => 0,
                            ]);
                        } catch (\Throwable $e) {
                            // Row telah diinsert oleh transaksi paralel
                        }
                        $counter = $counterModel::where('queue_date', $today)->lockForUpdate()->first();
                    }

                    $counter->last_number += 1;
                    $counter->save();

                    $code = str_pad($counter->last_number, 3, '0', STR_PAD_LEFT);

                    return QueueTicket::create([
                        'code' => $code,
                        'nim' => $validated['nim'],
                        'name' => trim($validated['name']),
                        'study_program' => $validated['study_program'],
                        'service_category' => $validated['service_category'],
                        'notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                        'queue_date' => $today,
                        'status' => 'waiting',
                        'device_token' => $deviceToken,
                    ]);
                });

                break; // Berhasil
            } catch (QueryException $e) {
                // Jika collision pada unique constraint (queue_date, code), lakukan retry
                if ($attempt === $maxRetries) {
                    throw $e;
                }
                usleep(50000); // 50ms jeda
            }
        }

        return redirect()->route('queue.status')
            ->cookie('device_token', $deviceToken, 1440, null, null, $isSecure, true, false, 'Lax');
    }

    /**
     * Tampilkan halaman status antrian mahasiswa.
     */
    public function status(Request $request)
    {
        $deviceToken = $request->cookie('device_token');

        if (!$deviceToken || !Str::isUuid($deviceToken)) {
            return redirect()->route('queue.take');
        }

        $ticket = QueueTicket::with('counter')
            ->where('device_token', $deviceToken)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return redirect()->route('queue.take');
        }

        $queueData = $this->getTicketQueueData($ticket);

        return view('queue-tickets.status', [
            'ticket' => $ticket,
            'queueAhead' => $queueData['queueAhead'],
            'currentCallSummary' => $queueData['currentCallSummary'],
            'counterName' => $queueData['counterName'],
            'counters' => $queueData['counters'],
        ]);
    }

    /**
     * Endpoint polling status tiket (JSON).
     */
    public function statusJson(Request $request)
    {
        $deviceToken = $request->cookie('device_token');

        if (!$deviceToken || !Str::isUuid($deviceToken)) {
            return response()->json(['success' => false, 'message' => 'No cookie found or invalid token'], 400);
        }

        $ticket = QueueTicket::with('counter')
            ->where('device_token', $deviceToken)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'No active ticket'], 404);
        }

        $queueData = $this->getTicketQueueData($ticket);

        return response()->json([
            'success' => true,
            'code' => $ticket->code,
            'status' => $ticket->status,
            'queue_ahead' => $queueData['queueAhead'],
            'counter_name' => $queueData['counterName'],
            'current_called_summary' => $queueData['currentCallSummary'] ?: '-',
            'current_called_code' => $queueData['currentCallSummary'] ?: '-',
        ]);
    }

    /**
     * Helper privat untuk menghitung posisi antrian (queue_ahead) dan panggilan saat ini per loket.
     * Sesuai ketentuan: queue_ahead HANYA dihitung jika status tiket 'waiting'.
     */
    private function getTicketQueueData(QueueTicket $ticket): array
    {
        $queueAhead = 0;

        if ($ticket->status === 'waiting') {
            $queueAhead = QueueTicket::where('status', 'waiting')
                ->where('id', '<', $ticket->id)
                ->count();
        }

        $counters = QueueCounter::with(['queueState.currentTicket'])->orderBy('id')->get();
        $callsList = [];

        foreach ($counters as $c) {
            $code = $c->queueState?->currentTicket?->code;
            $callsList[] = $c->name . ': ' . ($code ?: '-');
        }

        $currentCallSummary = implode(' | ', $callsList);

        return [
            'queueAhead' => $queueAhead,
            'currentCallSummary' => $currentCallSummary,
            'counterName' => $ticket->counter?->name ?? ($ticket->counter_id ? 'Loket ' . $ticket->counter_id : null),
            'counters' => $counters,
        ];
    }

    /**
     * Tampilkan halaman monitor publik (TV/layar ruang tunggu).
     * Tidak memerlukan login. Hanya menampilkan nama loket dan nomor yang dipanggil.
     */
    public function display()
    {
        $counters = QueueCounter::with(['queueState.currentTicket'])->orderBy('id')->get();
        return view('queue-tickets.display', compact('counters'));
    }

    /**
     * JSON polling untuk halaman monitor publik.
     * DILARANG menyertakan data pribadi: nama, NIM, prodi, layanan, catatan.
     */
    public function displayJson()
    {
        $counters = QueueCounter::with(['queueState.currentTicket'])->orderBy('id')->get();

        $data = $counters->map(function ($c) {
            return [
                'id'          => $c->id,
                'name'        => $c->name,
                'current_number' => $c->queueState?->currentTicket?->code ?? null,
            ];
        });

        return response()->json(['counters' => $data]);
    }

    /**
     * Cari data mahasiswa berdasarkan NIM untuk auto-fill di form ambil antrian.
     */
    public function lookupStudent(Request $request)
    {
        $nim = trim((string) $request->query('nim'));

        if (!$nim) {
            return response()->json([
                'found' => false,
                'message' => 'NIM wajib diisi.',
            ], 400);
        }

        $student = \App\Models\Student::where('student_number', $nim)->first();

        if (!$student) {
            return response()->json([
                'found' => false,
                'message' => 'Mahasiswa tidak ditemukan di database.',
            ], 404);
        }

        // Cari prodi yang cocok dengan daftar config queue.study_programs
        $configuredProdis = config('queue.study_programs', []);
        $matchedProdi = null;
        $studentProdi = (string) $student->study_program;

        foreach ($configuredProdis as $cp) {
            if (strcasecmp($cp, $studentProdi) === 0) {
                $matchedProdi = $cp;
                break;
            }
            // Cek jika nama prodi terkandung (misal: "S1 Informatika - Kampus Surabaya" cocok ke "Informatika")
            if (stripos($studentProdi, $cp) !== false) {
                $matchedProdi = $cp;
                break;
            }
        }

        return response()->json([
            'found' => true,
            'nim' => $student->student_number,
            'name' => $student->name,
            'study_program' => $matchedProdi ?? $student->study_program,
            'raw_study_program' => $student->study_program,
        ]);
    }
}
