<?php

use App\Models\Lecturer;
use App\Models\StudentFinalProject;
use App\Services\SidangTaBookingIdentity;
use App\Services\SidangTaBookingService;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;
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
test('bookingui111_confirmed_student_sees_schedule_without_mutation_controls', function () {
    $s = $this->s;
    $s['settings']->update(['duration_minutes' => 90]);
    $service = app(\App\Services\SidangTaStaffScheduling::class);
    $p = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30');
    $service->confirm($s['reviewer'], $s['registration'], $p['options'][0]['token']);
    $this->actingAs($s['owner'])->get(route('sidang-ta-execution.show', $s['registration']))->assertOk()->assertDontSee('Perubahan jadwal ditangani')->assertSee('08:00')->assertSee('09:30')
        ->assertDontSee('name="date"', false)->assertDontSee('Batalkan jadwal');
    $this->post(route('admin.sidang-ta-bookings.cancel', $s['registration']), ['context_version' => 2, 'reason' => 'test'])->assertForbidden();
});
test('bookingui111_admin_management_cards_are_separate_from_review', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-registrations.index'))->assertOk()->assertDontSee('data-manage-ta-execution', false)->assertSee('Kelola Periode');
    $this->get(route('admin.sidang-ta-execution.index'))->assertOk()->assertSee('Kelola Pelaksanaan');
    $this->get(route('admin.sidang-ta-execution.show', $s['registration']))->assertOk()->assertSee('Cari ketersediaan')
        ->assertSeeInOrder(['Plotting Jadwal', 'Tipe TA', 'Individu'])->assertDontSee('Dijadwalkan melalui dosen');
});

test('plotting editor appears on eligible staff detail with the imported values and room options', function () {
    $s = $this->s;
    $s['rooms']['lab']->update(['label' => 'LAB']);
    $s['rooms']['unknown']->update(['label' => 'LAB']);
    Lecturer::create(['lecturer_code' => 'EX1', 'name' => 'Penguji Satu']);
    $s['registration']->roomSchedule()->create([
        'student_number' => $s['student']->student_number,
        'planning_source' => 'import',
        'preferred_date' => '2026-11-24',
        'preferred_start_time' => '08:30',
        'preferred_end_time' => '09:40',
        'preferred_room_id' => $s['rooms']['lab']->id,
        'participant_data' => ['examiner_1_code' => 'EX1', 'ta_type' => 'Kelompok'],
    ]);
    $adminDetail = route('admin.sidang-ta-execution.show', $s['registration']);
    $response = $this->actingAs($s['reviewer'])->get($adminDetail)->assertOk()
        ->assertSee('Edit Data')->assertDontSee('Cek LAB dari Excel');
    $editor = (new Crawler($response->getContent()))->filter('form:has(input[name="editing_plotting"])');
    expect($editor->attr('action'))->toBe(route('admin.sidang-ta-execution.plotting', $s['registration']))
        ->and($editor->filter('input[name="date"]')->attr('value'))->toBe('2026-11-24')
        ->and($editor->filter('input[name="start_time"]')->attr('value'))->toBe('08:30')
        ->and($editor->attr('x-data'))->toContain('sidang-ta-execution', 'plotting-rooms')
        ->and($editor->filter('select[name="ta_type"]')->attr('x-on:change'))->toBe('loadRooms()')
        ->and($editor->filter('select[name="ta_type"] option[selected]')->attr('value'))->toBe('Kelompok')
        ->and($editor->filter('select[name="examiner_1_code"] option[selected]')->attr('value'))->toBe('EX1');

    $roomOptions = route('admin.sidang-ta-execution.plotting-rooms', $s['registration']);
    $this->getJson($roomOptions.'?date=2026-11-24&start_time=08:30&end_time=09:40&ta_type=Individu')->assertOk()
        ->assertJsonFragment(['id' => $s['rooms']['lab']->id])->assertJsonMissing(['id' => $s['rooms']['unknown']->id]);
    $this->getJson($roomOptions.'?date=2026-11-24&start_time=08:30&end_time=09:40&ta_type=Kelompok')->assertOk()
        ->assertJsonFragment(['id' => $s['rooms']['unknown']->id]);

    $this->actingAs($s['owner'])->get(route('sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSeeInOrder(['Plotting Jadwal', 'Tipe TA', 'Kelompok'])->assertDontSee('Edit Data');
    $s['settings']->update(['is_active' => false]);
    $this->actingAs($s['reviewer'])->get($adminDetail)->assertOk()->assertDontSee('Edit Data');
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
    $this->actingAs($s['reviewer'])->post(route('admin.sidang-ta-execution.preview', $s['registration']), ['date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30'])->assertOk()->assertSee('Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji');
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
