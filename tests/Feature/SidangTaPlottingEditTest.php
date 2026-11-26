<?php

use App\Models\RoomBookingRequest;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->plottingUrl = route('admin.sidang-ta-execution.plotting', $this->s['registration']);
    $this->plottingData = ['context_version' => 0, 'date' => '2026-11-23', 'start_time' => '12:00', 'end_time' => '12:50',
        'room_id' => $this->s['rooms']['b']->id, 'examiner_1_code' => 'P01', 'examiner_2_code' => 'P02'];
});

test('staff edits all plotting fields on an unassigned submission without reserving a room', function () {
    $this->actingAs($this->s['owner'])->postJson($this->plottingUrl, $this->plottingData)->assertForbidden();
    $this->actingAs($this->s['reviewer'])->post($this->plottingUrl, $this->plottingData)->assertSessionHasNoErrors()->assertRedirect();

    $schedule = $this->s['registration']->roomSchedule()->sole();
    expect($schedule->preferred_date->toDateString())->toBe('2026-11-23')
        ->and($schedule->preferred_end_time)->toBe('12:50:00')
        ->and($schedule->preferred_room_id)->toBe($this->s['rooms']['b']->id)
        ->and($schedule->participant_data)->toBe(['examiner_1_code' => 'P01', 'examiner_2_code' => 'P02'])
        ->and($schedule->executionLogs()->first()->action)->toBe('plotting_updated')
        ->and(RoomBookingRequest::count())->toBe(0);
    $this->postJson($this->plottingUrl, $this->plottingData)->assertConflict();
});

test('staff can correct elapsed bookings while preserving the confirmed participant snapshot and checking conflicts', function () {
    $scheduling = app(SidangTaStaffScheduling::class);
    $s = $this->s;
    $preview = $scheduling->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30');
    $context = $scheduling->confirm($s['reviewer'], $s['registration'], $preview['options'][0]['token']);
    $s['registration']->update(['student_name' => 'Nama Akun Diperbarui']);
    $this->travelTo(Carbon::parse('2026-11-30 09:00', 'Asia/Jakarta'));

    $busy = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['b']->id,
        'type' => 'single', 'start_date' => '2026-11-24', 'end_date' => '2026-11-24', 'start_time' => '13:00', 'end_time' => '15:15',
        'purpose' => 'Peminjaman lain', 'participants' => 1, 'status' => 'approved', 'booking_origin' => 'ordinary']);
    $busy->occurrences()->create(['usage_date' => '2026-11-24', 'start_time' => '13:00', 'end_time' => '15:15']);
    $data = ['context_version' => $context->lock_version, 'date' => '2026-11-24', 'start_time' => '13:00', 'end_time' => '14:10',
        'reason' => 'Koreksi jadwal aktual', 'examiner_2_code' => null] + $this->plottingData;
    $this->actingAs($s['reviewer'])->postJson($this->plottingUrl, $data)->assertUnprocessable()->assertJsonValidationErrors('room_id');
    expect($context->fresh()->booking->occurrences->sole()->usage_date->toDateString())->toBe('2026-11-23');

    $data['start_time'] = '09:30';
    $data['end_time'] = '10:40';
    $this->post($this->plottingUrl, $data)->assertSessionHasNoErrors()->assertRedirect();
    $updated = $context->fresh();
    expect($updated->booking->id)->toBe($context->booking->id)
        ->and($updated->booking->room_id)->toBe($s['rooms']['b']->id)
        ->and($updated->booking->occurrences->sole()->usage_date->toDateString())->toBe('2026-11-24')
        ->and($updated->state)->toBe('completed')->and($updated->active_student_number)->toBeNull()
        ->and($updated->details_snapshot['student_name'])->toBe($context->details_snapshot['student_name'])
        ->and($updated->details_snapshot['examiner_1_code'])->toBe('P01')->and($updated->details_snapshot['examiner_2_code'])->toBe('');

    $updated->update(['result_status' => 'passed']);
    $data['context_version'] = $updated->lock_version;
    $this->postJson($this->plottingUrl, $data)->assertUnprocessable()->assertJsonValidationErrors('schedule');
});

test('editing examiner codes keeps an imported lab only for its original slot', function () {
    $lab = $this->s['rooms']['lab'];
    $lab->update(['label' => 'LAB']);
    $context = $this->s['registration']->roomSchedule()->create(['student_number' => $this->s['student']->student_number,
        'planning_source' => 'import', 'preferred_date' => '2026-11-23', 'preferred_start_time' => '12:00:00',
        'preferred_end_time' => '12:50:00', 'preferred_room_id' => $lab->id]);
    $data = ['context_version' => $context->lock_version, 'room_id' => $lab->id] + $this->plottingData;
    $this->actingAs($this->s['reviewer'])->postJson($this->plottingUrl, ['end_time' => '12:55'] + $data)
        ->assertUnprocessable()->assertJsonValidationErrors('room_id');
    $this->post($this->plottingUrl, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($context->fresh()->planning_source)->toBe('import');
});
