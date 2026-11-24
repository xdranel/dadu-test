<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\RoomBookingRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SidebarBadgeService
{
    /**
     * Cache TTL (detik) untuk badge antrian.
     */
    protected const TTL = 60;

    /**
     * Hitung angka badge untuk key tertentu.
     *
     * Antrian menggunakan cache singkat. Peminjaman dihitung saat sidebar dirender.
     */
    public function count(string $key, ?User $actor = null): int
    {
        return match ($key) {
            'queue_pending' => $this->cached(
                'sidebar.badge.queue_pending',
                fn (): int => QueueTicket::whereDate('created_at', today())
                    ->where('status', 'waiting')
                    ->count()
            ),
            'room_booking_pending' => $this->pendingRoomBookings($actor),
            default => 0,
        };
    }

    private function pendingRoomBookings(?User $actor): int
    {
        if (! $actor || (! $actor->hasRole('Akademik') && ! $actor->can('room_booking_review'))) {
            return 0;
        }

        return RoomBookingRequest::awaitingUse()
            ->where('status', 'pending')
            ->when(! $actor->can('sidang_ta_registration_review'), fn ($query) => $query->whereNull('sidang_ta_registration_id'))
            ->count();
    }

    /**
     * Bungkus callback dengan cache berdurasi pendek.
     */
    protected function cached(string $cacheKey, \Closure $callback): int
    {
        return (int) Cache::remember($cacheKey, self::TTL, $callback);
    }
}
