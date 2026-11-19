<?php

use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->executionScenario = TaRoomScenario::make();
    $this->executionScenario['settings']->update(['duration_minutes' => 90,
        'break_start_time' => null, 'break_end_time' => null]);
});

test('student has a separate readonly execution page showing their TA identity', function () {
    $s = $this->executionScenario;
    $this->actingAs($s['owner'])->get('/dashboard/sidang-ta-execution')->assertOk()
        ->assertSee('Info Sidang')->assertSee($s['student']->student_number)->assertSee('Riwayat Pelaksanaan');
});

test('student cannot search or confirm through the legacy booking endpoints', function () {
    $s = $this->executionScenario;
    $this->actingAs($s['owner']);
    $this->post('/dashboard/sidang-ta-bookings/'.$s['registration']->getRouteKey().'/search', [])
        ->assertForbidden();
    $this->post('/dashboard/sidang-ta-bookings/'.$s['registration']->getRouteKey().'/confirm', [])
        ->assertForbidden();
    expect($s['registration']->roomSchedule()->exists())->toBeFalse();
});

test('execution admin index lists approved participants independently from execution settings', function () {
    $s = $this->executionScenario;
    $this->actingAs($s['reviewer'])->get('/dashboard/admin/sidang-ta-execution')->assertOk()
        ->assertSee($s['student']->student_number)->assertSee('Ringkasan hasil filter')->assertSee('Kelola Pelaksanaan');
});

test('student cannot write an execution result', function () {
    $s = $this->executionScenario;
    $this->actingAs($s['owner'])->post('/dashboard/admin/sidang-ta-execution/'.$s['registration']->getRouteKey().'/outcome',
        ['status' => 'passed', 'context_version' => 0])->assertForbidden();
    expect($s['registration']->fresh()->status)->toBe('approved');
});
