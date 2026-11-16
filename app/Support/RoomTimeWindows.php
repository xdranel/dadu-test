<?php

namespace App\Support;

use InvalidArgumentException;

final class RoomTimeWindows
{
    public static function merge(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn ($v) => $v['start'] < $v['end']));
        usort($intervals, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $result = [];
        foreach ($intervals as $slot) {
            $i = count($result) - 1;
            if ($i >= 0 && $slot['start'] <= $result[$i]['end']) {
                $result[$i]['end'] = max($result[$i]['end'], $slot['end']);
            } else {
                $result[] = ['start' => (int) $slot['start'], 'end' => (int) $slot['end']];
            }
        }

        return $result;
    }

    public static function firstFit(array $busy, array $allowed, int $durationMinutes, int $notBeforeMinute = 0): ?array
    {
        if ($durationMinutes < 1) {
            throw new InvalidArgumentException('Durasi harus positif.');
        }
        $busy = self::merge($busy);
        usort($allowed, fn ($a, $b) => $a['start'] <=> $b['start']);
        foreach ($allowed as $window) {
            $cursor = max($notBeforeMinute, $window['start']);
            foreach ($busy as $slot) {
                if ($slot['end'] <= $cursor || $slot['start'] >= $window['end']) {
                    continue;
                }
                if ($durationMinutes <= $slot['start'] - $cursor) {
                    return ['start' => $cursor, 'end' => $cursor + $durationMinutes];
                }
                $cursor = max($cursor, $slot['end']);
            }
            if ($durationMinutes <= $window['end'] - $cursor) {
                return ['start' => $cursor, 'end' => $cursor + $durationMinutes];
            }
        }

        return null;
    }

    public static function minutes(string $time, bool $ceil = false): int
    {
        if (! preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/D', $time, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
            throw new InvalidArgumentException('Jam tidak valid.');
        }

        return (int) $m[1] * 60 + (int) $m[2] + ($ceil && (int) ($m[3] ?? 0) > 0 ? 1 : 0);
    }

    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes,60), $minutes % 60);
    }
}
