<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Models\QueueTicketHistory;
use App\Models\QueueState;
use App\Services\QueueCounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QueueController extends Controller
{
    protected QueueCounterService $counterService;

    public function __construct(QueueCounterService $counterService)
    {
        $this->counterService = $counterService;
    }

    /**
     * Display the admin queue dashboard with statistics
     */
    public function index(Request $request)
    {
        $today = now('Asia/Jakarta')->toDateString();
        $user = $request->user();

        // Ambil loket yang aktif untuk user ini (jika ada)
        $selectedCounter = $this->counterService->selectedFor($request, $user);

        // Ambil seluruh master loket beserta status aktif dan tiket yang sedang dilayani
        $counters = QueueCounter::with(['activeUser', 'queueState.currentTicket'])->orderBy('id')->get();

        // State untuk loket yang dipilih petugas
        $state = null;
        $currentCall = null;
        $isBackActive = false;

        if ($selectedCounter) {
            $state = QueueState::firstOrCreate(
                ['counter_id' => $selectedCounter->id],
                ['current_id' => null, 'last_id' => null]
            );
            $currentCall = $state->current_id ? QueueTicket::find($state->current_id) : null;
            $isBackActive = $state->last_id !== null;
        }

        // Statistik per-loket hari ini
        $counterStats = [];
        foreach ($counters as $c) {
            $counterStats[$c->id] = [
                'finished_today' => QueueTicket::where('counter_id', $c->id)
                    ->whereDate('created_at', $today)
                    ->where('status', 'finished')
                    ->count(),
                'avg_service_time' => $this->getCounterAvgServiceTime($c->id, $today),
            ];
        }

        // Today's ticket statistics (global)
        $todayStats = $this->getTodayStats($today);

        // Ticket lists
        $waitingTickets = QueueTicket::whereDate('created_at', $today)
            ->where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get();

        $calledTickets = QueueTicket::with(['counter', 'calledByUser'])
            ->whereDate('created_at', $today)
            ->where('status', 'called')
            ->orderBy('called_at', 'desc')
            ->get();

        $finishedTickets = QueueTicket::with(['counter', 'calledByUser'])
            ->whereDate('created_at', $today)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'desc')
            ->get();

        // History statistics
        $historyStats = $this->getHistoryStats();

        return view('queue-tickets.admin', compact(
            'counters',
            'selectedCounter',
            'counterStats',
            'state',
            'currentCall',
            'isBackActive',
            'todayStats',
            'historyStats',
            'waitingTickets',
            'calledTickets',
            'finishedTickets'
        ));
    }

    /**
     * Klaim loket oleh petugas
     */
    public function claim(Request $request)
    {
        $validated = $request->validate([
            'counter_id' => 'required|integer|exists:queue_counters,id',
        ]);

        $result = $this->counterService->claim($request, $request->user(), (int) $validated['counter_id']);

        if (!$result['ok']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Berhasil memilih ' . $result['counter']->name . '.');
    }

    /**
     * Lepas loket oleh petugas
     */
    public function release(Request $request)
    {
        $finishCurrent = $request->boolean('finish_current');
        $result = $this->counterService->release($request, $request->user(), $finishCurrent);

        if (!$result['ok'] && $result['needs_confirmation']) {
            return redirect()->back()
                ->with('queue_release_ticket', $result['ticket']->code)
                ->with('error', 'Masih ada nomor ' . $result['ticket']->code . ' yang sedang dilayani. Selesaikan dulu?');
        }

        return redirect()->back()->with('success', 'Berhasil keluar dari loket.');
    }

    /**
     * JSON endpoint for auto-refresh of dashboard stats
     */
    public function stats(Request $request)
    {
        $today = now('Asia/Jakarta')->toDateString();
        $user = $request->user();

        $selectedCounter = $this->counterService->selectedFor($request, $user);
        $counters = QueueCounter::with(['activeUser', 'queueState.currentTicket'])->orderBy('id')->get();

        $state = null;
        $currentCall = null;
        $isBackActive = false;

        if ($selectedCounter) {
            $state = QueueState::firstOrCreate(
                ['counter_id' => $selectedCounter->id],
                ['current_id' => null, 'last_id' => null]
            );
            $currentCall = $state->current_id ? QueueTicket::find($state->current_id) : null;
            $isBackActive = $state->last_id !== null;
        }

        $counterData = [];
        foreach ($counters as $c) {
            $counterData[] = [
                'id' => $c->id,
                'name' => $c->name,
                'active_user_name' => $c->activeUser?->name,
                'is_current_user' => (int) $c->active_user_id === (int) $user->id,
                'current_code' => $c->queueState?->currentTicket?->code ?? '-',
                'called_at' => $c->queueState?->currentTicket?->called_at?->format('H:i') ?? '-',
                'finished_today' => QueueTicket::where('counter_id', $c->id)
                    ->whereDate('created_at', $today)
                    ->where('status', 'finished')
                    ->count(),
                'avg_service_time' => $this->getCounterAvgServiceTime($c->id, $today),
            ];
        }

        $todayStats = $this->getTodayStats($today);

        $waitingTickets = QueueTicket::whereDate('created_at', $today)
            ->where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get();

        $calledTickets = QueueTicket::with(['counter', 'calledByUser'])
            ->whereDate('created_at', $today)
            ->where('status', 'called')
            ->orderBy('called_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'code' => $t->code,
                    'nim' => $t->nim,
                    'name' => $t->name,
                    'study_program' => $t->study_program,
                    'service_category' => $t->service_category,
                    'notes' => $t->notes,
                    'called_at_formatted' => $t->called_at ? $t->called_at->format('H:i') : '-',
                    'counter_name' => $t->counter?->name ?? '-',
                    'caller_name' => $t->calledByUser?->name ?? '-',
                ];
            });

        $finishedTickets = QueueTicket::with(['counter', 'calledByUser'])
            ->whereDate('created_at', $today)
            ->where('status', 'finished')
            ->orderBy('finished_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'code' => $t->code,
                    'nim' => $t->nim,
                    'name' => $t->name,
                    'study_program' => $t->study_program,
                    'service_category' => $t->service_category,
                    'notes' => $t->notes,
                    'finished_at_formatted' => $t->finished_at ? $t->finished_at->format('H:i') : '-',
                    'counter_name' => $t->counter?->name ?? '-',
                    'caller_name' => $t->calledByUser?->name ?? '-',
                ];
            });

        return response()->json([
            'success' => true,
            'selectedCounter' => $selectedCounter ? [
                'id' => $selectedCounter->id,
                'name' => $selectedCounter->name,
            ] : null,
            'counters' => $counterData,
            'state' => [
                'current_id' => $state?->current_id,
                'last_id' => $state?->last_id,
                'current_called_code' => $currentCall ? $currentCall->code : null,
                'current_called_ticket' => $currentCall ? [
                    'code' => $currentCall->code,
                    'nim' => $currentCall->nim,
                    'name' => $currentCall->name,
                    'study_program' => $currentCall->study_program,
                    'service_category' => $currentCall->service_category,
                    'notes' => $currentCall->notes,
                ] : null,
                'is_back_active' => $isBackActive,
            ],
            'todayStats' => $todayStats,
            'tickets' => [
                'waiting' => $waitingTickets,
                'called' => $calledTickets,
                'finished' => $finishedTickets,
            ],
        ]);
    }

    /**
     * Polling endpoint for the admin dashboard (legacy compatibility)
     */
    public function indexJson(Request $request)
    {
        return $this->stats($request);
    }

    /**
     * Get today's statistics from queue_tickets
     */
    private function getTodayStats(string $today): array
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        $waitSql = $isSqlite
            ? 'AVG((strftime("%s", called_at) - strftime("%s", created_at)) / 60.0) as avg_wait'
            : 'AVG(TIMESTAMPDIFF(MINUTE, created_at, called_at)) as avg_wait';
        $serviceSql = $isSqlite
            ? 'AVG((strftime("%s", finished_at) - strftime("%s", called_at)) / 60.0) as avg_service'
            : 'AVG(TIMESTAMPDIFF(MINUTE, called_at, finished_at)) as avg_service';

        $todayQuery = QueueTicket::whereDate('created_at', $today);

        $totalToday = (clone $todayQuery)->count();
        $waitingToday = (clone $todayQuery)->where('status', 'waiting')->count();
        $calledToday = (clone $todayQuery)->where('status', 'called')->count();
        $finishedToday = (clone $todayQuery)->where('status', 'finished')->count();

        $avgWaitTime = QueueTicket::whereDate('created_at', $today)
            ->whereNotNull('called_at')
            ->selectRaw($waitSql)
            ->value('avg_wait');

        $avgServiceTime = QueueTicket::whereDate('created_at', $today)
            ->whereNotNull('finished_at')
            ->whereNotNull('called_at')
            ->selectRaw($serviceSql)
            ->value('avg_service');

        $lastTicket = QueueTicket::whereDate('created_at', $today)
            ->orderBy('id', 'desc')
            ->first();

        return [
            'totalToday' => $totalToday,
            'waitingToday' => $waitingToday,
            'calledToday' => $calledToday,
            'finishedToday' => $finishedToday,
            'avgWaitTime' => $avgWaitTime !== null ? round((float)$avgWaitTime, 1) : null,
            'avgServiceTime' => $avgServiceTime !== null ? round((float)$avgServiceTime, 1) : null,
            'lastTicketCode' => $lastTicket ? $lastTicket->code : null,
        ];
    }

    /**
     * Rata-rata waktu layanan per counter hari ini (menit)
     */
    private function getCounterAvgServiceTime(int $counterId, string $today): ?float
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        $serviceSql = $isSqlite
            ? 'AVG((strftime("%s", finished_at) - strftime("%s", called_at)) / 60.0) as avg_service'
            : 'AVG(TIMESTAMPDIFF(MINUTE, called_at, finished_at)) as avg_service';

        $avg = QueueTicket::where('counter_id', $counterId)
            ->whereDate('created_at', $today)
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereNotNull('called_at')
            ->selectRaw($serviceSql)
            ->value('avg_service');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * Get history statistics from queue_ticket_histories
     */
    private function getHistoryStats(): array
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        $waitSql = $isSqlite
            ? 'AVG((strftime("%s", called_at) - strftime("%s", created_at)) / 60.0) as avg_wait'
            : 'AVG(TIMESTAMPDIFF(MINUTE, created_at, called_at)) as avg_wait';

        $yesterday = now('Asia/Jakarta')->subDay()->toDateString();
        $mondayThisWeek = now('Asia/Jakarta')->startOfWeek(Carbon::MONDAY)->toDateString();
        $today = now('Asia/Jakarta')->toDateString();

        $yesterdayQuery = QueueTicketHistory::whereDate('created_at', $yesterday);
        $totalYesterday = (clone $yesterdayQuery)->count();
        $finishedYesterday = (clone $yesterdayQuery)->where('status', 'finished')->count();
        $avgWaitYesterday = QueueTicketHistory::whereDate('created_at', $yesterday)
            ->whereNotNull('called_at')
            ->selectRaw($waitSql)
            ->value('avg_wait');

        $weekQuery = QueueTicketHistory::whereDate('created_at', '>=', $mondayThisWeek)
            ->whereDate('created_at', '<=', $today);
        $totalWeek = (clone $weekQuery)->count();
        $finishedWeek = (clone $weekQuery)->where('status', 'finished')->count();
        $avgWaitWeek = QueueTicketHistory::whereDate('created_at', '>=', $mondayThisWeek)
            ->whereDate('created_at', '<=', $today)
            ->whereNotNull('called_at')
            ->selectRaw($waitSql)
            ->value('avg_wait');

        return [
            'yesterday' => [
                'total' => $totalYesterday,
                'finished' => $finishedYesterday,
                'avgWaitTime' => $avgWaitYesterday !== null ? round((float)$avgWaitYesterday, 1) : null,
            ],
            'thisWeek' => [
                'total' => $totalWeek,
                'finished' => $finishedWeek,
                'avgWaitTime' => $avgWaitWeek !== null ? round((float)$avgWaitWeek, 1) : null,
            ],
        ];
    }

    /**
     * Panggil nomor antrian berikutnya untuk loket petugas yang sedang aktif
     */
    public function next(Request $request)
    {
        $user = $request->user();
        $selectedCounter = $this->counterService->selectedFor($request, $user);

        if (!$selectedCounter) {
            return redirect()->back()->with('error', 'Silakan pilih loket terlebih dahulu sebelum memanggil antrian.');
        }

        $result = DB::transaction(function () use ($selectedCounter, $user) {
            $state = QueueState::where('counter_id', $selectedCounter->id)
                ->lockForUpdate()
                ->firstOrCreate(['counter_id' => $selectedCounter->id]);

            // Selesaikan tiket called milik loket ini jika ada
            if ($state->current_id) {
                $currentTicket = QueueTicket::lockForUpdate()->find($state->current_id);
                if ($currentTicket && $currentTicket->status === 'called' && (int) $currentTicket->counter_id === (int) $selectedCounter->id) {
                    $currentTicket->update([
                        'status' => 'finished',
                        'finished_at' => now(),
                    ]);
                    $state->last_id = $currentTicket->id;
                } else {
                    $state->last_id = null;
                }
            } else {
                $state->last_id = null;
            }

            // Ambil tiket waiting tertua hari ini dengan lockForUpdate
            $today = now('Asia/Jakarta')->toDateString();
            $nextTicket = QueueTicket::where('status', 'waiting')
                ->where(function ($q) use ($today) {
                    $q->where('queue_date', $today)->orWhereNull('queue_date');
                })
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            // Panggil tiket berikutnya
            if ($nextTicket) {
                $nextTicket->update([
                    'status' => 'called',
                    'called_at' => now(),
                    'counter_id' => $selectedCounter->id,
                    'called_by' => $user->id,
                ]);

                $state->current_id = $nextTicket->id;
                $state->save();

                return [
                    'status' => 'success',
                    'message' => 'Memanggil nomor antrian ' . $nextTicket->code . ' di ' . $selectedCounter->name,
                ];
            } else {
                $state->current_id = null;
                $state->save();

                if ($state->last_id) {
                    return [
                        'status' => 'success',
                        'message' => 'Antrian saat ini telah selesai. Tidak ada antrian menunggu.',
                    ];
                }

                return [
                    'status' => 'error',
                    'message' => 'Tidak ada antrian.',
                ];
            }
        });

        if ($result['status'] === 'success') {
            return redirect()->back();
        }

        return redirect()->back()->with($result['status'], $result['message']);
    }

    /**
     * Batalkan pemanggilan terakhir khusus untuk loket petugas ini (mundur 1 langkah)
     */
    public function back(Request $request)
    {
        $user = $request->user();
        $selectedCounter = $this->counterService->selectedFor($request, $user);

        if (!$selectedCounter) {
            return redirect()->back()->with('error', 'Silakan pilih loket terlebih dahulu.');
        }

        $result = DB::transaction(function () use ($selectedCounter) {
            $state = QueueState::where('counter_id', $selectedCounter->id)->lockForUpdate()->first();

            if (!$state || !$state->last_id) {
                return [
                    'status' => 'error',
                    'message' => 'Tidak bisa membatalkan pemanggilan (maksimal mundur 1 langkah).',
                ];
            }

            // Kembalikan tiket current milik loket ini ke 'waiting'
            if ($state->current_id) {
                $currentTicket = QueueTicket::lockForUpdate()->find($state->current_id);
                if ($currentTicket && $currentTicket->status === 'called' && (int) $currentTicket->counter_id === (int) $selectedCounter->id) {
                    $currentTicket->update([
                        'status' => 'waiting',
                        'called_at' => null,
                        'counter_id' => null,
                        'called_by' => null,
                    ]);
                }
            }

            // Kembalikan tiket last milik loket ini ke 'called'
            $lastTicket = QueueTicket::lockForUpdate()->find($state->last_id);
            if ($lastTicket && $lastTicket->status === 'finished' && (int) $lastTicket->counter_id === (int) $selectedCounter->id) {
                $lastTicket->update([
                    'status' => 'called',
                    'called_at' => now(),
                    'finished_at' => null,
                ]);
                $state->current_id = $lastTicket->id;
            } else {
                $state->current_id = null;
            }

            // Reset last_id loket ini
            $state->last_id = null;
            $state->save();

            return [
                'status' => 'success',
                'message' => 'Pemanggilan antrian sebelumnya di ' . $selectedCounter->name . ' berhasil dibatalkan.',
            ];
        });

        return redirect()->back()->with($result['status'], $result['message']);
    }
}
