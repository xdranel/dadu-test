<?php

use App\Models\Lecturer;
use App\Models\RoomBookingRequest;
use App\Models\User;
use App\Services\SidangTaExecutionInfoService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
});

test('execution participant page and settings are separate pages', function () {
    $this->actingAs($this->s['reviewer'])->get('/dashboard/admin/sidang-ta-execution')
        ->assertOk()->assertSee('Ringkasan hasil filter')->assertSee($this->s['student']->student_number)
        ->assertSee('Impor plotting')->assertSee('Export Excel');
    $this->get('/dashboard/admin/sidang-ta-execution/settings')->assertOk()
        ->assertSee('Semester sumber jadwal')->assertSee('90')->assertSee('Kembali');
});

test('student execution pages have identity cards and no scheduling controls', function () {
    $s = $this->s;
    $this->actingAs($s['owner'])->get('/dashboard/sidang-ta-execution')->assertOk()
        ->assertSee('Peserta TA')->assertSee('Plotting Jadwal')->assertSee('Riwayat Pelaksanaan')
        ->assertDontSee('Tetapkan Jadwal')->assertDontSee('Cari ketersediaan');
    $this->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertOk()
        ->assertSee($s['student']->student_number)->assertDontSee('ID Pengajuan');
    $other = User::factory()->create(['status' => 'active']);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertForbidden();
});

test('period reader cannot inspect participants and participant identifiers use UUIDs', function () {
    $reader = User::factory()->create(['status' => 'active']);
    $reader->givePermissionTo('sidang_ta_period_read');
    $this->actingAs($reader)->get('/dashboard/admin/sidang-ta-execution')->assertForbidden();
    $this->get('/dashboard/admin/sidang-ta-execution/settings')->assertOk();
    $this->actingAs($this->s['reviewer'])->get('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->id)
        ->assertClientError()->assertDontSee($this->s['student']->student_number);
});

test('program reader cannot expand execution filters or write results or export', function () {
    Permission::findOrCreate('sidang_ta_registration_view_program', 'web');
    $reader = User::factory()->create(['status' => 'active']);
    $reader->givePermissionTo('sidang_ta_registration_view_program');
    Lecturer::create(['lecturer_code' => 'EXEC', 'name' => 'Pembaca Pelaksanaan', 'email_domain' => $reader->email, 'study_program' => 'Informatika']);
    $foreign = $this->s['registration']->replicate();
    $foreign->student_number = '2220001';
    $foreign->student_name = 'Peserta Prodi Lain';
    $foreign->study_program = 'Sistem Informasi';
    $foreign->save();
    $this->actingAs($reader)->get('/dashboard/admin/sidang-ta-execution?study_program=Sistem%20Informasi')->assertOk()
        ->assertSee($this->s['student']->student_number)->assertDontSee('Peserta Prodi Lain')->assertDontSee('Export Excel');
    $this->get('/dashboard/admin/sidang-ta-execution/'.$foreign->getRouteKey())->assertForbidden();
    $this->get('/dashboard/admin/sidang-ta-execution/export')->assertForbidden();
    $this->post('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->getRouteKey().'/outcome', ['status' => 'passed', 'context_version' => 0])->assertForbidden();
});

test('manual plotting import requires a file while system scheduling does not', function () {
    $this->actingAs($this->s['reviewer'])->post('/dashboard/admin/sidang-ta-execution/import', ['period' => $this->s['period']->getRouteKey()])
        ->assertSessionHasErrors('file');
    $this->get('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->getRouteKey())->assertOk()
        ->assertSee('Cari ketersediaan')->assertDontSee('Impor terlebih dahulu');
});

test('staff date picker is bounded by execution dates and excludes past days', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSee('min="2026-11-23"', false)->assertSee('max="2026-11-27"', false);
    $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'Asia/Jakarta'));
    $this->get(route('admin.sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSee('min="2026-11-24"', false)->assertSee('max="2026-11-27"', false);
    $this->post(route('admin.sidang-ta-execution.preview', $s['registration']), ['date' => '2026-11-28'])
        ->assertSessionHasErrors('date');
    expect(RoomBookingRequest::count())->toBe(0);
});

test('active info ends after actual occurrence while historical detail keeps its snapshot', function () {
    $s = $this->s;
    $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id,
        'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23',
        'start_time' => '08:00', 'end_time' => '09:30', 'purpose' => 'Sidang', 'participants' => 1,
        'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $s['registration']->id]);
    $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number, 'state' => 'confirmed',
        'was_confirmed' => true, 'room_booking_request_id' => $booking->id, 'participant_data' => ['title' => 'Judul saat ditetapkan']]);
    app(SidangTaExecutionInfoService::class)->capture($s['registration']);
    $s['registration']->roomSchedule()->update(['participant_data' => json_encode(['title' => 'Judul terbaru'])]);
    $this->travelTo(Carbon::parse('2026-11-23 09:30:01', 'Asia/Jakarta'));
    $this->actingAs($s['owner'])->get('/dashboard/sidang-ta-execution')->assertOk()->assertSee('Belum ada pelaksanaan sidang aktif.');
    $this->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertOk()
        ->assertSee('Judul saat ditetapkan')->assertDontSee('Judul terbaru')->assertSee('08:00')->assertSee('09:30');
});
