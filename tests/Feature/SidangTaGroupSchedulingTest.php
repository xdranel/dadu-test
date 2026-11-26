<?php

use App\Models\RoomBookingRequest;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->s['rooms']['lab']->update(['label' => 'LAB']);
});

test('group schedules can share exactly the same lab or classroom slot within their period and program', function (string $roomKey) {
    $s = $this->s;
    $target = $s['rooms'][$roomKey];
    $service = app(SidangTaStaffScheduling::class);
    $first = $s['registration'];
    $this->actingAs($s['reviewer'])->post(route('admin.sidang-ta-execution.plotting', $first), [
        'context_version' => 0, 'date' => '2026-11-23', 'start_time' => '10:00', 'end_time' => '11:00',
        'examiner_1_code' => null, 'examiner_2_code' => null, 'ta_type' => 'Kelompok',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($first->roomSchedule()->sole()->taType())->toBe('Kelompok');
    expect(RoomBookingRequest::query()->exists())->toBeFalse();
    $select = fn ($registration, $start = '10:00', $end = '11:00') => collect($service->preview($s['reviewer'], $registration, '2026-11-23', $start, $end)['options'])->firstWhere('candidate.room_id', $target->id);
    $second = $first->replicate(['public_id']);
    $second->student_number = '1110002';
    $second->save();
    $second->roomSchedule()->create(['student_number' => $second->student_number, 'participant_data' => ['ta_type' => 'Kelompok']]);

    $option = $select($first);
    expect($option)->not->toBeNull();
    $firstBooking = $service->confirm($s['reviewer'], $first, $option['token']);
    expect($select($second, '10:30', '11:00'))->toBeNull();
    $second->update(['study_program' => 'Prodi Lain']);
    expect($select($second))->toBeNull();
    $second->update(['study_program' => $first->study_program]);
    $option = $select($second);
    expect($option)->not->toBeNull();
    $secondBooking = $service->confirm($s['reviewer'], $second, $option['token']);
    expect($secondBooking->booking->room_id)->toBe($target->id);

    $this->actingAs($s['reviewer'])->postJson(route('admin.sidang-ta-execution.plotting', $first), [
        'context_version' => $firstBooking->lock_version, 'date' => '2026-11-23', 'start_time' => '10:00', 'end_time' => '11:00',
        'room_id' => $target->id, 'examiner_1_code' => null, 'examiner_2_code' => null, 'ta_type' => 'Individu',
    ])->assertUnprocessable();
    app(\App\Services\SidangTaBookingAdministration::class)->cancel($s['reviewer'], $first, $firstBooking->lock_version, 'Uji pemisahan jadwal');
    expect($secondBooking->fresh()->booking->status)->toBe('approved');
    expect(app(\App\Services\RoomAvailabilityService::class)->busyIntervals($target->id, '2026-11-23'))->toBe([['start' => 600, 'end' => 660]]);
})->with(['a', 'lab']);

test('group availability still respects pending ordinary booking and an individual TA booking', function () {
    $s = $this->s;
    $service = app(SidangTaStaffScheduling::class);
    $reg = $s['registration'];
    $first = $service->preview($s['reviewer'], $reg, '2026-11-23', '10:00', '11:00')['options'][0];
    $service->confirm($s['reviewer'], $reg, $first['token']);
    $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['lab']->id, 'type' => 'single',
        'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '10:00', 'end_time' => '11:00',
        'purpose' => 'Kegiatan umum', 'participants' => 1, 'status' => 'pending', 'booking_origin' => 'ordinary']);
    $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);
    $group = $reg->replicate(['public_id']);
    $group->student_number = '1110002';
    $group->save();
    $group->roomSchedule()->create(['student_number' => $group->student_number, 'participant_data' => ['ta_type' => 'Kelompok']]);
    $options = $service->preview($s['reviewer'], $group, '2026-11-23', '10:00', '11:00')['options'];
    expect(collect($options)->pluck('candidate.room_id'))->not->toContain($first['candidate']['room_id'], $s['rooms']['lab']->id);
});
