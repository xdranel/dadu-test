<?php

namespace App\Support;

use Carbon\Carbon;

class QueueSchedule
{
    /**
     * Nama hari dalam bahasa Indonesia.
     */
    protected static array $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * Dapatkan instance Carbon untuk saat ini (default Asia/Jakarta).
     */
    public static function now(?Carbon $now = null): Carbon
    {
        return ($now ? $now->copy() : Carbon::now('Asia/Jakarta'))->setTimezone('Asia/Jakarta');
    }

    /**
     * Cek apakah saat ini waktu pengambilan tiket sedang buka.
     */
    public static function isOpen(?Carbon $now = null): bool
    {
        $current = self::now($now);
        $dayOfWeek = $current->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu
        $schedule = config("queue.schedule.{$dayOfWeek}", []);
        $openBeforeMinutes = (int) config('queue.open_before_minutes', 5);

        foreach ($schedule as $session) {
            $ticketOpenTime = Carbon::createFromTimeString($session['start'], 'Asia/Jakarta')
                ->setDate($current->year, $current->month, $current->day)
                ->subMinutes($openBeforeMinutes);

            $sessionCloseTime = Carbon::createFromTimeString($session['end'], 'Asia/Jakarta')
                ->setDate($current->year, $current->month, $current->day);

            if ($current->greaterThanOrEqualTo($ticketOpenTime) && $current->lessThan($sessionCloseTime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dapatkan deskripsi teks jam buka berikutnya dalam bahasa Indonesia.
     * Contoh: "Hari ini pukul 12:55 WIB" atau "Senin pukul 08:55 WIB".
     */
    public static function nextOpening(?Carbon $now = null): ?string
    {
        $current = self::now($now);
        $openBeforeMinutes = (int) config('queue.open_before_minutes', 5);

        // Cari sesi buka berikutnya dalam 7 hari ke depan
        for ($dayOffset = 0; $dayOffset <= 7; $dayOffset++) {
            $checkDate = $current->copy()->addDays($dayOffset);
            $dayOfWeek = $checkDate->dayOfWeekIso;
            $sessions = config("queue.schedule.{$dayOfWeek}", []);

            foreach ($sessions as $session) {
                $ticketOpenTime = Carbon::createFromTimeString($session['start'], 'Asia/Jakarta')
                    ->setDate($checkDate->year, $checkDate->month, $checkDate->day)
                    ->subMinutes($openBeforeMinutes);

                if ($ticketOpenTime->greaterThan($current)) {
                    $timeStr = $ticketOpenTime->format('H:i') . ' WIB';

                    if ($dayOffset === 0) {
                        return "Hari ini pukul {$timeStr}";
                    } elseif ($dayOffset === 1) {
                        return "Besok (" . self::$dayNames[$dayOfWeek] . ") pukul {$timeStr}";
                    } else {
                        return self::$dayNames[$dayOfWeek] . " pukul {$timeStr}";
                    }
                }
            }
        }

        return null;
    }

    /**
     * Ambil rincian jam layanan untuk ditampilkan di view closed/info.
     */
    public static function getScheduleSummary(): array
    {
        return [
            'Senin - Kamis' => [
                'Sesi 1' => '09:00 - 12:00 WIB (Tiket: 08:55 - 12:00)',
                'Sesi 2' => '13:00 - 15:00 WIB (Tiket: 12:55 - 15:00)',
            ],
            'Jumat' => [
                'Sesi 1' => '09:00 - 11:30 WIB (Tiket: 08:55 - 11:30)',
                'Sesi 2' => '13:00 - 15:00 WIB (Tiket: 12:55 - 15:00)',
            ],
            'Sabtu & Minggu' => [
                'Status' => 'Tutup / Libur Akhir Pekan',
            ],
        ];
    }
}
