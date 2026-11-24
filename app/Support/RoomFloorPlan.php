<?php

namespace App\Support;

use App\Models\Room;
use Illuminate\Support\Collection;

final class RoomFloorPlan
{
    // Rows specify left, top, room width, room height, and the labels in the supplied plan.
    private const PLANS = [
        'a1' => [
            'label' => 'Gedung A · Lantai 1', 'floor' => '1', 'width' => 1063, 'height' => 168, 'prefix' => 'KTT',
            'rows' => [
                [79, 8, 124, 54, ['A-1.07', 'B-1.07', 'B-1.08']],
                [610, 8, 124, 54, ['1.27 B', 'B-1.06', 'B-1.05']],
                [1, 76, 63, 83, ['1.22', '1.21', '1.20', '1.19', '1.18', '1.17', '1.16', 'A-1.06']],
                [556, 76, 63, 83, ['1.09', '1.08', '1.07', '1.06', '1.05', '1.04', '1.03', '1.02']],
            ],
        ],
        'a2' => [
            'label' => 'Gedung A · Lantai 2', 'floor' => '2', 'width' => 1063, 'height' => 168, 'prefix' => 'KTT',
            'rows' => [
                [1, 8, 63, 55, ['2.37', '2.36', '2.35', 'A-1.04', 'A-1.03', '2.32']],
                [684, 8, 63, 55, ['2.28', '2.27', '2.26', '2.25', '2.24', '2.23']],
                [1, 78, 60, 81, ['2.21', '2.20', '2.19', '2.18', '2.17', '2.16', '2.15']],
                [582, 78, 60, 81, ['2.09', '2.08', '2.07', '2.06', '2.05', '2.04', '2.03', '2.02']],
            ],
        ],
        'b1' => [
            'label' => 'Gedung B · Lantai 1', 'floor' => '1', 'width' => 856, 'height' => 286, 'prefix' => '',
            'rows' => [
                [70, 8, 205, 70, ['B-CLS-101', 'B-CLS-102']],
                [291, 110, 205, 78, ['B-CLS-105']],
                [560, 110, 296, 168, ['B-CLS-104']],
                [291, 202, 269, 76, ['B-CLS-106']],
            ],
        ],
    ];

    public static function arrange(Collection $rooms): array
    {
        $roomKey = fn (Room $room) => WeeklyRoomScheduleNormalizer::roomKey($room->room_code);
        $byCode = $rooms->groupBy($roomKey);
        $plans = [];

        foreach (self::positions() as $code => $position) {
            $matches = $byCode->get($code);
            if ($matches?->count() !== 1) {
                continue;
            }

            $plan = $position['plan'];
            $plans[$plan] ??= ['key' => $plan] + array_intersect_key(self::PLANS[$plan], array_flip(['label', 'floor', 'width', 'height']));
            unset($position['plan']);
            $plans[$plan]['tiles'][] = ['room' => $matches->first()] + $position;
        }

        return array_values($plans);
    }

    private static function positions(): array
    {
        static $positions = null;
        if ($positions !== null) {
            return $positions;
        }

        $positions = [];
        foreach (self::PLANS as $plan => $layout) {
            foreach ($layout['rows'] as [$left, $top, $width, $height, $labels]) {
                foreach ($labels as $index => $label) {
                    $code = WeeklyRoomScheduleNormalizer::roomKey($layout['prefix'].$label);
                    $positions[$code] = [
                        'plan' => $plan,
                        'label' => $label,
                        'left' => 100 * ($left + $index * $width) / $layout['width'],
                        'top' => 100 * $top / $layout['height'],
                        'width' => 100 * ($width - 3) / $layout['width'],
                        'height' => 100 * $height / $layout['height'],
                    ];
                }
            }
        }

        return $positions;
    }
}
