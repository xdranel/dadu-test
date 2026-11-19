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

test('empty search identifies both unclassified classrooms and missing semester source', function () {
    Room::query()->update(['is_classroom' => null]);
    $this->s['source']->delete();
    $result = app(SidangTaStaffScheduling::class)->preview($this->s['reviewer'], $this->s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])
        ->toContain('Klasifikasi', 'sumber', 'Ganjil 2026');
});

test('complete source does not make unclassified rooms eligible for TA', function () {
    Room::query()->update(['is_classroom' => null]);
    $result = app(SidangTaStaffScheduling::class)->preview($this->s['reviewer'], $this->s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])->toContain('Klasifikasi');
    expect(Room::whereNotNull('is_classroom')->count())->toBe(0);
});

test('complete source and classified rooms report a genuinely full ninety minute window separately', function () {
    $s = $this->s;
    foreach ([$s['rooms']['a'], $s['rooms']['b']] as $room) {
        $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $room->id,
            'type' => 'single', 'purpose' => 'Terpakai', 'participants' => 1, 'status' => 'approved',
            'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '16:00']);
        $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '16:00']);
    }
    $result = app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23');
    expect($result['options'])->toBeEmpty()->and($result['reason'])->toContain('90 menit')->not->toContain('belum lengkap');
});
