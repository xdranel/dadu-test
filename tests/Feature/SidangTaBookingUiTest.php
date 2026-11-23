<?php

use App\Models\Lecturer;
use App\Models\StudentFinalProject;
use App\Services\SidangTaBookingIdentity;
use App\Services\SidangTaBookingService;
use Carbon\Carbon;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->s = TaRoomScenario::make();
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
});
test('bookingui111_student_reads_execution_in_separate_menu_and_cannot_search', function () {
    $s = $this->s;
    $this->actingAs($s['owner'])->get(route('sidang-ta-registrations.index'))->assertOk()->assertDontSee('data-ta-booking-card', false);
    $this->get(route('sidang-ta-bookings.index', $s['registration']))->assertRedirect(route('sidang-ta-execution.show', $s['registration']));
    $this->get(route('sidang-ta-execution.index'))->assertOk()->assertSee('Info Sidang')->assertSee('Plotting Jadwal')
        ->assertSee(route('sidang-ta-execution.show', $s['registration']))
        ->assertDontSee('name="room_id"', false)->assertDontSee('name="date"', false);
    $this->post(route('sidang-ta-bookings.search', $s['registration']), ['date' => '2026-11-23'])->assertForbidden();
});
test('bookingui111_confirmed_student_has_contact_ssc_and_no_mutation_controls', function () {
    $s = $this->s;
    $s['settings']->update(['duration_minutes' => 90]);
    $service = app(\App\Services\SidangTaStaffScheduling::class);
    $p = $service->preview($s['reviewer'], $s['registration'], '2026-11-23');
    $service->confirm($s['reviewer'], $s['registration'], $p['options'][0]['token']);
    $this->actingAs($s['owner'])->get(route('sidang-ta-execution.show', $s['registration']))->assertOk()->assertSee('SSC')->assertSee('08:00')->assertSee('09:30')
        ->assertDontSee('name="date"', false)->assertDontSee('Batalkan jadwal');
    $this->post(route('admin.sidang-ta-bookings.cancel', $s['registration']), ['context_version' => 2, 'reason' => 'test'])->assertForbidden();
});
test('bookingui111_admin_management_cards_are_separate_from_review', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.index'))->assertOk()->assertDontSee('data-manage-ta-execution', false)->assertSee('Kelola Periode');
    $this->get(route('admin.sidang-ta-execution.index'))->assertOk()->assertSee('Kelola Pelaksanaan');
    $this->get(route('admin.sidang-ta-execution.show', $s['registration']))->assertOk()->assertSee('Cari ketersediaan')->assertDontSee('Dijadwalkan melalui dosen');
});
test('bookingui111_identity_uses_source_codes_even_without_master_and_never_overwrites_profile', function () {
    $s = $this->s;
    $p = StudentFinalProject::create(['student_number' => $s['student']->student_number, 'advisor_1' => 'XYZ', 'advisor_2' => 'ABC']);
    Lecturer::create(['name' => 'Dosen Dummy', 'lecturer_code' => 'ABC']);
    $before = $p->fresh()->getRawOriginal();
    $i = app(SidangTaBookingIdentity::class)->forRegistration($s['registration']);
    expect($i['advisor_1']['code'])->toBe('XYZ')->and($i['advisor_2']['name'])->toBe('Dosen Dummy')->and($i['examiner_1']['code'])->toBe('Belum tersedia')->and($p->fresh()->getRawOriginal())->toBe($before);
});
it('review111_confirmation_explains_attendance_limit_and_withdrawal_booking_consequence', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->post(route('admin.sidang-ta-execution.preview', $s['registration']), ['date' => '2026-11-23'])->assertOk()->assertSee('Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji');
    $p = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $c = app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']);
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.show', $s['registration']))->assertOk()->assertSee('Booking ruangan tertaut akan ikut dibatalkan')->assertSee('#'.$c->booking->id)->assertSee('23/11/2026');
});
it('review111_withdrawal_modal_names_the_linked_booking_before_operator_confirms', function () {
    $s = $this->s;
    $p = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $c = app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']);
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.show', $s['registration']))->assertOk()->assertSee('Booking ruangan tertaut akan ikut dibatalkan')->assertSee('#'.$c->booking->id)->assertSee('23/11/2026');
});
