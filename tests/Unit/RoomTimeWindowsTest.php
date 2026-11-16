<?php

use App\Support\RoomTimeWindows;

it('uses the first continuous gap and never crosses the lunch break', function () {
    $allowed = [['start' => 480, 'end' => 720], ['start' => 780, 'end' => 960]];
    expect(RoomTimeWindows::firstFit([['start' => 480, 'end' => 570]], $allowed, 120))->toBe(['start' => 570, 'end' => 690]);
    expect(RoomTimeWindows::firstFit([['start' => 480, 'end' => 660]], $allowed, 120))->toBe(['start' => 780, 'end' => 900]);
    expect(RoomTimeWindows::firstFit([], $allowed, 120, 900))->toBeNull();
});
it('merges overlapping and touching occupancy and honors exact endpoints', function () {
    $allowed = [['start' => 480, 'end' => 960]];
    expect(RoomTimeWindows::firstFit([['start' => 400, 'end' => 510], ['start' => 500, 'end' => 570], ['start' => 570, 'end' => 600]], $allowed, 120))->toBe(['start' => 600, 'end' => 720]);
    expect(RoomTimeWindows::firstFit([['start' => 600, 'end' => 960]], $allowed, 120))->toBe(['start' => 480, 'end' => 600]);
});
it('rejects short gaps and invalid duration while using a not-before bound', function () {
    expect(RoomTimeWindows::firstFit([], [['start' => 480, 'end' => 540]], 120))->toBeNull();
    expect(RoomTimeWindows::firstFit([], [['start' => 480, 'end' => 720]], 120, 510))->toBe(['start' => 510, 'end' => 630]);
    expect(fn () => RoomTimeWindows::firstFit([], [['start' => 480, 'end' => 720]], 0))->toThrow(InvalidArgumentException::class);
});
