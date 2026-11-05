<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\QueueState;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueCounterService
{
    /**
     * Dapatkan loket yang sedang dipilih/dipegang oleh petugas saat ini.
     * Mengutamakan session, dan memulihkan dari DB jika user sebelumnya sudah memegang loket.
     */
    public function selectedFor(Request $request, User $user): ?QueueCounter
    {
        $sessionId = $request->session()->get('queue_counter_id');

        if ($sessionId) {
            $counter = QueueCounter::find($sessionId);
            if ($counter && (int) $counter->active_user_id === (int) $user->id) {
                $counter->update(['last_seen_at' => now()]);
                return $counter;
            }
        }

        // Cek jika user sudah terdaftar memegang loket di database (misal login dari browser lain)
        $counter = QueueCounter::where('active_user_id', $user->id)->first();
        if ($counter) {
            $request->session()->put('queue_counter_id', $counter->id);
            $counter->update(['last_seen_at' => now()]);
            return $counter;
        }

        // Jika tidak valid, bersihkan session
        $request->session()->forget('queue_counter_id');
        return null;
    }

    /**
     * Klaim loket secara atomik (dengan lockForUpdate).
     */
    public function claim(Request $request, User $user, int $counterId): array
    {
        return DB::transaction(function () use ($request, $user, $counterId) {
            // Kunci semua counter untuk mencegah race condition
            $counters = QueueCounter::orderBy('id')->lockForUpdate()->get();

            // Cek jika user ini sudah memegang loket lain
            $owned = $counters->firstWhere('active_user_id', $user->id);
            if ($owned) {
                $request->session()->put('queue_counter_id', $owned->id);
                if ($owned->id === $counterId) {
                    return [
                        'ok' => true,
                        'counter' => $owned,
                        'message' => null,
                    ];
                }

                return [
                    'ok' => false,
                    'counter' => $owned,
                    'message' => 'Anda masih bertugas di ' . $owned->name . '. Silakan keluar dari loket tersebut terlebih dahulu.',
                ];
            }

            // Dapatkan loket target
            $target = $counters->firstWhere('id', $counterId);
            if (!$target) {
                return [
                    'ok' => false,
                    'counter' => null,
                    'message' => 'Loket tidak ditemukan.',
                ];
            }

            if ($target->active_user_id && (int) $target->active_user_id !== (int) $user->id) {
                $occupantName = $target->activeUser?->name ?? 'petugas lain';
                return [
                    'ok' => false,
                    'counter' => null,
                    'message' => 'Loket tersebut sedang dipakai oleh ' . $occupantName . '.',
                ];
            }

            // Klaim loket
            $target->update([
                'active_user_id' => $user->id,
                'last_seen_at' => now(),
            ]);

            // Pastikan state untuk loket ini ada
            QueueState::firstOrCreate(
                ['counter_id' => $target->id],
                ['current_id' => null, 'last_id' => null]
            );

            $request->session()->put('queue_counter_id', $target->id);

            return [
                'ok' => true,
                'counter' => $target,
                'message' => null,
            ];
        });
    }

    /**
     * Lepas loket yang dipegang petugas.
     * Jika ada tiket berstatus 'called' di loket ini dan belum dikonfirmasi selesai, minta konfirmasi.
     */
    public function release(Request $request, User $user, bool $finishCurrent = false): array
    {
        $counterId = $request->session()->get('queue_counter_id');

        if (!$counterId) {
            $owned = QueueCounter::where('active_user_id', $user->id)->first();
            $counterId = $owned?->id;
        }

        if (!$counterId) {
            return ['ok' => true, 'needs_confirmation' => false];
        }

        $result = DB::transaction(function () use ($counterId, $user, $finishCurrent) {
            $counter = QueueCounter::lockForUpdate()->find($counterId);

            if (!$counter || (int) $counter->active_user_id !== (int) $user->id) {
                return ['ok' => true, 'needs_confirmation' => false];
            }

            $state = QueueState::where('counter_id', $counter->id)->lockForUpdate()->first();
            $current = ($state && $state->current_id) ? QueueTicket::lockForUpdate()->find($state->current_id) : null;
            $hasActiveCalledTicket = $current && $current->status === 'called' && (int) $current->counter_id === (int) $counter->id;

            // Jika masih ada tiket called dan petugas belum mengonfirmasi selesai
            if ($hasActiveCalledTicket && !$finishCurrent) {
                return [
                    'ok' => false,
                    'needs_confirmation' => true,
                    'ticket' => $current,
                ];
            }

            // Jika dikonfirmasi selesai, selesaikan tiket aktif
            if ($hasActiveCalledTicket && $finishCurrent) {
                $current->update([
                    'status' => 'finished',
                    'finished_at' => now(),
                ]);
            }

            if ($state) {
                $state->update([
                    'current_id' => null,
                    'last_id' => null,
                ]);
            }

            $counter->update([
                'active_user_id' => null,
                'last_seen_at' => now(),
            ]);

            return ['ok' => true, 'needs_confirmation' => false];
        });

        if ($result['ok']) {
            $request->session()->forget('queue_counter_id');
        }

        return $result;
    }

    /**
     * Lepas loket saat user logout. Selesaikan tiket aktif jika ada agar tidak terlantar tanpa pemilik.
     */
    public function releaseForLogout(User $user): void
    {
        DB::transaction(function () use ($user) {
            $counter = QueueCounter::where('active_user_id', $user->id)->lockForUpdate()->first();
            if (!$counter) {
                return;
            }

            $state = QueueState::where('counter_id', $counter->id)->lockForUpdate()->first();
            if ($state && $state->current_id) {
                $current = QueueTicket::lockForUpdate()->find($state->current_id);
                if ($current && $current->status === 'called') {
                    $current->update([
                        'status' => 'finished',
                        'finished_at' => now(),
                    ]);
                }
                $state->update([
                    'current_id' => null,
                    'last_id' => null,
                ]);
            }

            $counter->update([
                'active_user_id' => null,
                'last_seen_at' => now(),
            ]);
        });
    }
}
