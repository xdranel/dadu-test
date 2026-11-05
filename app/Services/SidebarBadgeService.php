<?php

namespace App\Services;

use App\Models\QueueTicket;
use Illuminate\Support\Facades\Cache;

class SidebarBadgeService
{
    /**
     * Cache TTL (detik) untuk semua badge counter.
     */
    protected const TTL = 60;

    /**
     * Hitung angka badge untuk key tertentu.
     *
     * Sidebar dirender di setiap halaman, jadi hasil di-cache singkat
     * via Cache::remember() supaya tidak query berat tiap request.
     */
    public function count(string $key): int
    {
        return match ($key) {
            'queue_pending' => $this->cached(
                'sidebar.badge.queue_pending',
                fn (): int => QueueTicket::whereDate('created_at', today())
                    ->where('status', 'waiting')
                    ->count()
            ),
            default => 0,
        };
    }

    /**
     * Bungkus callback dengan cache berdurasi pendek.
     */
    protected function cached(string $cacheKey, \Closure $callback): int
    {
        return (int) Cache::remember($cacheKey, self::TTL, $callback);
    }
}
