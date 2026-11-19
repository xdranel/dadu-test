<?php

use App\Models\RoomBookingRequest;
use App\Models\SidangTaSchedule;
use App\Services\SidangTaBookingService;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    config(['sidang_ta.student_booking_enabled' => false]);
    $this->s117 = TaRoomScenario::make();
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
});

test('visibility117_student_booking_is_closed_when_disabled_even_after_approval', function () {
    expect(config('sidang_ta.student_booking_enabled', false))->toBeFalse();
    $s = $this->s117;
    $before = $s['registration']->fresh()->getRawOriginal();
    $this->actingAs($s['owner'])->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee('Draft tersimpan')->assertSee('Riwayat Pengajuan')
        ->assertDontSee('data-ta-booking-card', false)->assertDontSee('Cari jadwal sidang')
        ->assertDontSee(route('sidang-ta-bookings.index', $s['registration']))
        ->assertViewHas('bookingRegistrations', fn ($rows) => $rows->isEmpty());
    expect($s['registration']->fresh()->getRawOriginal())->toBe($before);
});

test('visibility117_student_routes_are_blocked_before_payload_validation', function ($action, $payload) {
    $s = $this->s117;
    $before = $s['registration']->fresh()->getRawOriginal();
    $this->actingAs($s['owner']);
    if ($action === 'index') {
        $this->get(route('sidang-ta-bookings.index', $s['registration']))->assertNotFound();
    } else {
        $this->post(route('sidang-ta-bookings.'.$action, $s['registration']), $payload)->assertNotFound();
    }
    expect(RoomBookingRequest::count())->toBe(0)->and(SidangTaSchedule::count())->toBe(0)
        ->and($s['registration']->fresh()->getRawOriginal())->toBe($before);
})->with([
    'direct page' => ['index', []],
    'valid search' => ['search', ['date' => '2026-11-23']],
    'empty search' => ['search', []],
    'empty confirm' => ['confirm', []],
]);

test('visibility117_a_preview_obtained_before_closure_cannot_be_confirmed', function () {
    $s = $this->s117;
    $preview = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $this->actingAs($s['owner'])->post(route('sidang-ta-bookings.confirm', $s['registration']),
        ['preview_token' => $preview['preview_token']])->assertNotFound();
    expect(RoomBookingRequest::count())->toBe(0)->and(SidangTaSchedule::count())->toBe(0);
});

test('visibility117_hiding_student_access_preserves_existing_booking_and_history', function () {
    $s = $this->s117;
    $service = app(SidangTaBookingService::class);
    $preview = $service->preview($s['owner'], $s['registration'], '2026-11-23');
    $context = $service->confirm($s['owner'], $s['registration'], $preview['preview_token']);
    $before = $context->booking->fresh()->getRawOriginal();
    $historyCount = $context->booking->histories()->count();
    $this->actingAs($s['owner'])->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee('data-ta-booking-card', false)->assertDontSee('Lihat jadwal sidang');
    $this->get(route('sidang-ta-bookings.index', $s['registration']))->assertNotFound();
    expect($context->booking->fresh()->getRawOriginal())->toBe($before)
        ->and($context->booking->histories()->count())->toBe($historyCount)
        ->and($context->fresh()->state)->toBe('confirmed');
});

test('visibility117_staff_can_still_manage_execution_and_existing_bookings', function () {
    $s = $this->s117;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertSee('data-manage-ta-execution', false);
    $this->get(route('admin.sidang-ta-execution.index'))->assertOk()->assertSee('Pelaksanaan Sidang TA');
    $this->get(route('admin.sidang-ta-bookings.show', $s['registration']))->assertOk();
});
