<?php

use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->s['settings']->update(['duration_minutes' => 90]);
});

test('empty search identifies missing semester source without requiring classification', function () {
    $this->s['source']->delete();
    $result = app(SidangTaStaffScheduling::class)->preview($this->s['reviewer'], $this->s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])
        ->toContain('sumber', 'Ganjil 2026')->not->toContain('Dapat Dipakai')->not->toContain('Klasifikasi');
});

test('inactive room status is respected without silently enabling any master', function () {
    Room::query()->update(['status' => 'Tidak Dapat Dipakai']);
    $result = app(SidangTaStaffScheduling::class)->preview($this->s['reviewer'], $this->s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])->toContain('Dapat Dipakai')->not->toContain('Klasifikasi');
    expect(Room::where('status', 'Tidak Dapat Dipakai')->count())->toBe(4);
});

test('complete source and usable rooms report a genuinely full ninety minute window separately', function () {
    $s = $this->s;
    foreach ($s['rooms'] as $room) {
        $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $room->id,
            'type' => 'single', 'purpose' => 'Terpakai', 'participants' => 1, 'status' => 'approved',
            'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '16:00']);
        $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '16:00']);
    }
    $result = app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])->toContain('90 menit')->not->toContain('belum lengkap');
});
