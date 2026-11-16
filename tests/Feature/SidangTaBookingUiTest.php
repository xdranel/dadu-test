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
test('bookingui111_student_sees_approved_card_and_date_only_search', function () {
    $s = $this->s;
    $this->actingAs($s['owner'])->get(route('sidang-ta-registrations.index'))->assertOk()->assertSee('data-ta-booking-card', false)->assertSee(route('sidang-ta-bookings.index', $s['registration']));
    $response = $this->get(route('sidang-ta-bookings.index', $s['registration']))->assertOk()->assertSee('Cari jadwal')->assertDontSee('name="room_id"', false)->assertDontSee('name="start_time"', false);
    $this->post(route('sidang-ta-bookings.search', $s['registration']), ['date' => '2026-11-23'])->assertOk()->assertSee('Konfirmasi jadwal')->assertSee('08:00')->assertSee('Belum tersedia');
});
test('bookingui111_confirmed_student_has_contact_ssc_and_no_mutation_controls', function () {
    $s = $this->s;
    $p = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $this->actingAs($s['owner'])->post(route('sidang-ta-bookings.confirm', $s['registration']), ['preview_token' => $p['preview_token']])->assertRedirect();
    $this->get(route('sidang-ta-bookings.index', $s['registration']))->assertOk()->assertSee('SSC')->assertSee('Disahkan')->assertDontSee('name="date"', false)->assertDontSee('Batalkan booking');
    $this->post(route('admin.sidang-ta-bookings.cancel', $s['registration']), ['context_version' => 2, 'reason' => 'test'])->assertForbidden();
});
test('bookingui111_admin_management_cards_and_scoped_mutations', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.index'))->assertOk()->assertSee('data-manage-ta-execution', false)->assertSee('Kelola Periode');
    $this->get(route('admin.sidang-ta-bookings.show', $s['registration']))->assertOk()->assertSee('Dijadwalkan melalui dosen');
    $this->post(route('admin.sidang-ta-bookings.mark-lecturer', $s['registration']), ['context_version' => 0, 'reason' => 'Dosen mengatur'])->assertRedirect();
    $this->actingAs($s['owner'])->get(route('sidang-ta-bookings.index', $s['registration']))->assertOk()->assertSee('Dijadwalkan melalui dosen')->assertDontSee('name="date"', false);
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
    $this->actingAs($s['owner'])->post(route('sidang-ta-bookings.search', $s['registration']), ['date' => '2026-11-23'])->assertOk()->assertSee('Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji');
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
