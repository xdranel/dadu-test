<?php

use App\Models\RoomBookingRequest;
use App\Models\RoomWeeklyPattern;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
});
afterEach(function () {
    $this->travelBack();
});

it('repeats within semester including inactive KRS periods and never beyond semester bounds', function () {
    $s = $this->s;
    $s['academic']->update(['is_active' => false]);
    RoomWeeklyPattern::create(['academic_period_id' => $s['academic']->id, 'room_id' => $s['rooms']['a']->id, 'weekday' => 1, 'start_time' => '08:30:00', 'end_time' => '10:30:00', 'reference_date' => '2026-11-09', 'source_identity' => 'test']);
    $service = app(RoomAvailabilityService::class);
    expect($service->busyIntervals($s['rooms']['a']->id, '2026-11-23'))->toBe([['start' => 510, 'end' => 630]]);
    expect($service->busyIntervals($s['rooms']['a']->id, '2026-11-24'))->toBe([]);
    expect($service->busyIntervals($s['rooms']['a']->id, '2027-02-01'))->toBe([]);
    $s['academic']->update(['end_date' => '2026-11-23']);
    expect($service->busyIntervals($s['rooms']['a']->id, '2026-11-23'))->not->toBeEmpty();
});
it('unions dated legacy slots and pending approved bookings but ignores cancelled rejected', function () {
    $s = $this->s;
    $room = $s['rooms']['a'];
    RoomWeeklyPattern::create(['academic_period_id' => $s['academic']->id, 'room_id' => $room->id, 'weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'reference_date' => '2026-11-09', 'source_identity' => 'union']);
    DB::table('room_schedules')->insert(['room_id' => $room->id, 'usage_date' => '2026-11-23', 'start_time' => '08:30:00', 'end_time' => '09:30:00', 'source' => 'igracias']);
    $active = [];
    foreach (['pending', 'approved', 'cancelled', 'rejected'] as $i => $status) {
        $b = RoomBookingRequest::create(['user_id' => $s['owner']->id, 'room_id' => $room->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => sprintf('%02d:00:00', 10 + $i), 'end_time' => sprintf('%02d:00:00', 11 + $i), 'purpose' => 'Test', 'participants' => 1, 'status' => $status]);
        $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => $b->start_time, 'end_time' => $b->end_time]);
        $active[] = $b;
    }
    $service = app(RoomAvailabilityService::class);
    expect($service->busyIntervals($room->id, '2026-11-23'))->toBe([['start' => 480, 'end' => 570], ['start' => 600, 'end' => 720]]);
    expect($service->busyIntervals($room->id, '2026-11-23', $active[0]->id))->toBe([['start' => 480, 'end' => 570], ['start' => 660, 'end' => 720]]);
    expect($service->forRange('2026-11-23', '2026-11-24')['2026-11-23'][$room->id])->toBe($service->busyIntervals($room->id, '2026-11-23'));
});
it('reports unknown coverage outside a complete verified semester source', function () {
    $service = app(RoomAvailabilityService::class);
    expect($service->coverage('2026-11-23', '2026-11-27')['ready'])->toBeTrue();
    $this->s['source']->update(['is_complete' => false]);
    expect($service->coverage('2026-11-23', '2026-11-23'))->toMatchArray(['ready' => false, 'missing_dates' => ['2026-11-23']]);
    $this->s['source']->update(['is_complete' => true]);
    expect($service->coverage('2027-02-01', '2027-02-01')['ready'])->toBeFalse();
});
