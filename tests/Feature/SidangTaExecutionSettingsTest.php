<?php

use App\Models\RoomBookingRequest;
use App\Services\SidangTaExecutionWindowService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->s = TaRoomScenario::make();
    $this->travelTo(now()->setDate(2026, 11, 19));
    $this->values = ['academic_period_id' => $this->s['academic']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-27', 'allowed_weekdays' => [1, 2, 3, 4, 5], 'start_time' => '08:00', 'end_time' => '16:00', 'break_start_time' => '12:00', 'break_end_time' => '13:00', 'duration_minutes' => 120];
});

test('execution111_saves_versioned_window_without_changing_registration_dates', function () {
    $before = $this->s['period']->fresh()->getRawOriginal();
    $v = app(SidangTaExecutionWindowService::class)->save($this->s['reviewer'], $this->s['period'], $this->values, 1);
    expect($v->lock_version)->toBe(2)->and($v->start_date->toDateString())->toBe('2026-11-23')->and($this->s['period']->fresh()->getRawOriginal())->toBe($before);
});
test('execution111_rejects_invalid_windows', function (array $change) {
    expect(fn () => app(SidangTaExecutionWindowService::class)->save($this->s['reviewer'], $this->s['period'], array_replace($this->values, $change), 1))->toThrow(ValidationException::class);
    expect($this->s['settings']->fresh()->lock_version)->toBe(1);
})->with(array_map(fn ($v) => [$v], [['start_date' => '2026-11-28'], ['end_date' => '2027-02-01'], ['allowed_weekdays' => [8]], ['start_time' => '17:00'], ['break_end_time' => null], ['break_start_time' => '07:00'], ['duration_minutes' => 300]]));
test('execution111_stale_and_student_writes_are_rejected', function () {
    $this->actingAs($this->s['reviewer'])->put(route('admin.sidang-ta-execution.update', $this->s['period']), $this->values + ['lock_version' => 0])->assertConflict();
    $this->actingAs($this->s['owner'])->get(route('admin.sidang-ta-execution.index'))->assertForbidden();
    $this->put(route('admin.sidang-ta-execution.update', $this->s['period']), $this->values + ['lock_version' => 1])->assertForbidden();
    $this->actingAs($this->s['reviewer'])->get(route('admin.sidang-ta-execution.index'))->assertOk()->assertSee('Review Pelaksanaan Sidang TA');
});
test('execution111_window_cannot_cut_existing_booking_but_new_default_duration_can_change', function () {
    $b = RoomBookingRequest::create(['user_id' => $this->s['owner']->id, 'room_id' => $this->s['rooms']['a']->id, 'purpose' => 'Dummy TA', 'type' => 'single', 'participants' => 1, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $this->s['registration']->id]);
    $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']);
    expect(fn () => app(SidangTaExecutionWindowService::class)->save($this->s['reviewer'], $this->s['period'], array_replace($this->values, ['start_date' => '2026-11-24']), 1))->toThrow(ValidationException::class);
    app(SidangTaExecutionWindowService::class)->save($this->s['reviewer'], $this->s['period'], array_replace($this->values, ['duration_minutes' => 90]), 1);
    expect($b->occurrences()->first()->end_time)->toStartWith('10:00');
});
test('execution111_academic_identity_in_use_cannot_be_rewritten', function () {
    $this->s['reviewer']->givePermissionTo(Permission::findOrCreate('student_update', 'web'));
    $p = $this->s['academic'];
    $data = ['year' => $p->year, 'semester' => 2, 'label' => 'Genap', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31'];
    $this->actingAs($this->s['reviewer'])->put(route('academic_periods.update', $p), $data)->assertSessionHasErrors('year');
    expect($p->fresh()->semester)->toBe(1);
});
test('room edits save the label and can clear it without changing other details', function () {
    $r = $this->s['rooms']['a'];
    $data = $r->only(['room_code', 'name', 'building', 'capacity', 'status']);
    $this->actingAs($this->s['reviewer'])->get(route('rooms.edit', $r))->assertOk()
        ->assertSee('name="label"', false)->assertDontSee('igracias_aliases')->assertSee('LABEL DITULIS DENGAN HURUF BESAR');
    $this->put(route('rooms.update', $r), $data + ['label' => 'LAB'])->assertSessionHasNoErrors();
    expect($r->fresh()->label)->toBe('LAB');
    $this->put(route('rooms.update', $r), $data + ['label' => 'AULA'])->assertSessionHasErrors('label');
    expect($r->fresh()->label)->toBe('LAB');
    $this->put(route('rooms.update', $r), $data + ['label' => ''])->assertSessionHasNoErrors();
    expect($r->fresh()->label)->toBeNull()->and($r->fresh()->only(array_keys($data)))->toBe($data);
});
