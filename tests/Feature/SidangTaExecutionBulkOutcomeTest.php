<?php

use App\Models\RoomBookingRequest;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
});

function bulkOutcomeRegistration(array $scenario, string $studentNumber): SidangTaRegistration
{
    $registration = $scenario['registration']->replicate(['public_id']);
    $registration->fill([
        'student_number' => $studentNumber,
        'student_name' => 'Mahasiswa '.$studentNumber,
    ])->save();

    return $registration;
}

function bulkOutcomeSchedule(array $scenario, SidangTaRegistration $registration, string $date): SidangTaSchedule
{
    $booking = RoomBookingRequest::create([
        'user_id' => $scenario['reviewer']->id,
        'room_id' => $scenario['rooms']['a']->id,
        'type' => 'single',
        'start_date' => $date,
        'end_date' => $date,
        'start_time' => '08:00',
        'end_time' => '09:15',
        'purpose' => 'Uji hasil sidang',
        'participants' => 1,
        'status' => 'approved',
        'booking_origin' => 'sidang_ta',
        'sidang_ta_registration_id' => $registration->id,
    ]);
    $booking->occurrences()->create(['usage_date' => $date, 'start_time' => '08:00', 'end_time' => '09:15']);

    return $registration->roomSchedule()->create([
        'student_number' => $registration->student_number,
        'room_booking_request_id' => $booking->id,
        'state' => 'confirmed',
        'was_confirmed' => true,
    ]);
}

test('execution index selects only assigned participants and records a past and future result in one batch', function () {
    $first = $this->s['registration'];
    $second = bulkOutcomeRegistration($this->s, '000001001');
    $unassigned = bulkOutcomeRegistration($this->s, '000001002');
    $this->s['rooms']['a']->update(['room_code' => 'RUANG-TERPASANG']);
    $this->s['rooms']['b']->update(['room_code' => 'RUANG-PREFERENSI']);
    $unassigned->roomSchedule()->create([
        'student_number' => $unassigned->student_number,
        'preferred_date' => '2026-11-23',
        'preferred_start_time' => '08:00:00',
        'preferred_end_time' => '09:15:00',
        'preferred_room_id' => $this->s['rooms']['b']->id,
    ]);
    $past = bulkOutcomeSchedule($this->s, $first, '2026-11-23');
    $future = bulkOutcomeSchedule($this->s, $second, '2026-11-26');

    $this->actingAs($this->s['reviewer'])->get(route('admin.sidang-ta-execution.index'))
        ->assertOk()->assertSee('Ruangan')->assertSee('Aksi')->assertSee('Lulus terpilih')->assertSee('Gagal terpilih')
        ->assertSee('RUANG-TERPASANG')->assertDontSee('RUANG-PREFERENSI')
        ->assertSee('name="participants['.$first->getRouteKey().']"', false)
        ->assertSee('name="participants['.$second->getRouteKey().']"', false)
        ->assertDontSee('name="participants['.$unassigned->getRouteKey().']"', false);

    $this->post('/dashboard/admin/sidang-ta-execution/bulk-outcome', [
        'status' => 'passed',
        'participants' => [$first->getRouteKey() => $past->lock_version, $second->getRouteKey() => $future->lock_version],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($past->fresh()->result_status)->toBe('passed')->and($future->fresh()->result_status)->toBe('passed');
    expect($past->executionLogs()->sole()->action)->toBe('result_updated');
    $this->get(route('admin.sidang-ta-execution.index'))->assertOk()->assertDontSee('Lulus terpilih');
});

test('a stale schedule rolls back all selected results', function () {
    $first = $this->s['registration'];
    $second = bulkOutcomeRegistration($this->s, '000001001');
    $firstSchedule = bulkOutcomeSchedule($this->s, $first, '2026-11-23');
    $secondSchedule = bulkOutcomeSchedule($this->s, $second, '2026-11-25');

    $this->actingAs($this->s['reviewer'])->postJson('/dashboard/admin/sidang-ta-execution/bulk-outcome', [
        'status' => 'failed',
        'participants' => [$first->getRouteKey() => $firstSchedule->lock_version, $second->getRouteKey() => $secondSchedule->lock_version + 1],
    ])->assertConflict();

    expect($firstSchedule->fresh()->result_status)->toBe('pending')->and($secondSchedule->fresh()->result_status)->toBe('pending');
    expect($firstSchedule->executionLogs()->count())->toBe(0);
});

test('bulk result requires reviewer permission and an assignment, and cannot replace or cancel a final outcome', function () {
    $registration = $this->s['registration'];
    $url = '/dashboard/admin/sidang-ta-execution/bulk-outcome';
    $data = ['status' => 'failed', 'participants' => [$registration->getRouteKey() => 0]];
    $this->actingAs($this->s['owner'])->postJson($url, $data)->assertForbidden();
    $this->actingAs($this->s['reviewer'])->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($registration->roomSchedule()->exists())->toBeFalse();

    $schedule = bulkOutcomeSchedule($this->s, $registration, '2026-11-23');
    $schedule->update(['result_status' => 'passed']);
    $data['participants'][$registration->getRouteKey()] = $schedule->lock_version;
    $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson($url, ['status' => 'pending'] + $data)->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($schedule->fresh()->result_status)->toBe('passed');

    $this->post(route('admin.sidang-ta-execution.outcome', $registration), [
        'status' => 'pending', 'context_version' => $schedule->lock_version,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($schedule->fresh()->result_status)->toBe('pending');

    $data['participants'][$registration->getRouteKey()] = $schedule->fresh()->lock_version;
    $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($schedule->fresh()->result_status)->toBe('failed');
});
