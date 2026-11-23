<?php

use App\Models\AcademicPeriod;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\RoomScheduleSource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));

    // Setup basic scenario & permissions
    $this->scenario = TaRoomScenario::make();

    // Lecturer 1
    $this->lecturer = User::factory()->create(['auth_source' => 'sso', 'status' => 'active', 'employee_id' => 'DSN001', 'name' => 'Dr. Budi Santoso']);
    // Lecturer 2
    $this->lecturer2 = User::factory()->create(['auth_source' => 'sso', 'status' => 'active', 'employee_id' => 'DSN002', 'name' => 'Dr. Siti Aminah']);

    $pelaksanaRole = Role::findOrCreate('Pelaksana', 'web');
    $this->lecturer->assignRole($pelaksanaRole);
    $this->lecturer2->assignRole($pelaksanaRole);

    foreach (['room_booking_create', 'room_booking_read', 'room_booking_update', 'room_booking_review'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $this->lecturer->givePermissionTo(['room_booking_create', 'room_booking_read', 'room_booking_update']);
    $this->lecturer2->givePermissionTo(['room_booking_create', 'room_booking_read', 'room_booking_update']);

    // Admin Akademik
    $this->admin = $this->scenario['reviewer'];
    $akademikRole = Role::findOrCreate('Akademik', 'web');
    $this->admin->assignRole($akademikRole);
    $this->admin->givePermissionTo(['room_booking_review', 'room_booking_read', 'room_booking_create', 'room_booking_update']);

    // Make sure iGracias import log exists so hasSuccessfulImport is true
    DB::table('room_import_logs')->insert([
        'user_id' => $this->admin->id,
        'filename' => 'jadwal-semester.json',
        'rows_imported' => 10,
        'errors' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Active period: 2026-09-01 s.d. 2027-01-31 (from scenario)
});

afterEach(function () {
    $this->travelBack();
});

// =========================================================================
// 1. REVIEW PEMINJAMAN RUANGAN (ADMIN AKADEMIK)
// =========================================================================

test('academic admin can access review page with list of bookings and calendar', function () {
    $room = $this->scenario['rooms']['a'];
    $room->update(['room_code' => 'KTT-REVIEW-01']);
    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['a']->id,
        'type' => 'single',
        'start_date' => '2026-11-25',
        'end_date' => '2026-11-25',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'purpose' => 'Rapat Koordinasi Dosen',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $booking->occurrences()->create([
        'usage_date' => '2026-11-25',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.room-bookings.review'));
    $response->assertOk();
    $response->assertSee('Review Peminjaman Ruangan');
    $response->assertSee('Rapat Koordinasi Dosen');
    $response->assertSee($room->room_code)->assertDontSee($room->name);
});

test('academic admin can approve a pending room booking with or without a note', function (?string $note) {
    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['b']->id,
        'type' => 'single',
        'start_date' => '2026-11-26',
        'end_date' => '2026-11-26',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'purpose' => 'Ujian Susulan',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $booking->occurrences()->create([
        'usage_date' => '2026-11-26',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.room-bookings.decision', $booking), [
        'decision' => 'approved',
        'note' => $note ?? '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('approved');
    expect($booking->fresh()->admin_note)->toBe($note);
})->with([['Disetujui untuk ujian susulan'], [null]]);

test('academic admin CANNOT approve booking if room status is inactive / tidak dapat dipakai (mutlak ditolak)', function () {
    // Ruangan sedang tidak dapat dipakai
    $this->scenario['rooms']['b']->update(['status' => 'Tidak Dapat Dipakai']);

    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['b']->id,
        'type' => 'single',
        'start_date' => '2026-11-26',
        'end_date' => '2026-11-26',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'purpose' => 'Seminar Proposal',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $booking->occurrences()->create([
        'usage_date' => '2026-11-26',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.room-bookings.decision', $booking), [
        'decision' => 'approved',
        'note' => 'Coba setujui',
    ]);

    $response->assertSessionHasErrors('availability');
    expect($booking->fresh()->status)->toBe('pending');
});

test('academic admin can reject a pending booking with or without a note', function (?string $note) {
    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['b']->id,
        'type' => 'single',
        'start_date' => '2026-11-26',
        'end_date' => '2026-11-26',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'purpose' => 'Kegiatan Tambahan',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $booking->occurrences()->create([
        'usage_date' => '2026-11-26',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.room-bookings.decision', $booking), [
        'decision' => 'rejected',
        'note' => $note ?? '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('rejected');
    expect($booking->fresh()->admin_note)->toBe($note);
})->with([['Ruangan akan dipakai agenda pimpinan'], [null]]);

test('academic admin can cancel an approved booking with reason', function () {
    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['b']->id,
        'type' => 'single',
        'start_date' => '2026-11-28',
        'end_date' => '2026-11-28',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'purpose' => 'Kuliah Pengganti',
        'participants' => 0,
        'status' => 'approved',
        'booking_origin' => 'ordinary',
    ]);
    $booking->occurrences()->create([
        'usage_date' => '2026-11-28',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.room-bookings.cancel', $booking), [
        'reason' => 'Pemeliharaan mendadak instalasi listrik',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('cancelled');
    expect($booking->fresh()->cancellation_reason)->toBe('Pemeliharaan mendadak instalasi listrik');
});

test('non-reviewer user cannot access review page', function () {
    $this->actingAs($this->lecturer)->get(route('admin.room-bookings.review'))->assertForbidden();
});

// =========================================================================
// 2. DASHBOARD PEMINJAMAN RUANGAN (DOSEN / PELAKSANA)
// =========================================================================

test('lecturer can access dashboard and view active bookings', function () {
    $response = $this->actingAs($this->lecturer)->get(route('room-bookings.index'));
    $response->assertOk();
    $response->assertSee('Dashboard Peminjaman Ruangan');
    $response->assertSee('Kalender Ketersediaan Ruangan');
});

test('manual lecturer can submit a booking and see the academic approval in staging', function () {
    $this->app->instance('env', 'staging');
    $csrfToken = str()->random(40);
    $this->withSession(['_token' => $csrfToken])->withHeader('X-CSRF-TOKEN', $csrfToken);
    $manualLecturer = User::factory()->create([
        'name' => 'Dosen Simulasi Sidang TA',
        'email' => 'dosen.ta@dosen.test',
        'auth_source' => 'manual',
        'status' => 'active',
    ]);
    $role = Role::findByName('Pelaksana');
    $manualLecturer->assignRole($role);
    $manualLecturer->givePermissionTo(['room_booking_create', 'room_booking_read', 'room_booking_update']);

    $this->actingAs($manualLecturer)->get(route('room-bookings.index'))->assertOk();
    $this->get(route('room-bookings.create'))->assertOk();
    $this->get(route('room-bookings.my'))->assertOk();
    $this->get(route('admin.room-bookings.review'))->assertForbidden();

    $room = $this->scenario['rooms']['a'];
    $slot = ['start_at' => '2026-11-24T10:00', 'end_at' => '2026-11-24T11:00'];
    $purpose = 'Peminjaman kelas untuk kegiatan dosen';
    $this->postJson(route('room-bookings.availability'), $slot)->assertOk()->assertJsonFragment(['id' => $room->id]);
    $this->post(route('room-bookings.store'), $slot + ['room_id' => $room->id, 'purpose' => $purpose])
        ->assertSessionHasNoErrors()->assertRedirect(route('room-bookings.index'));
    $booking = RoomBookingRequest::where('purpose', $purpose)->sole();
    expect($booking->user_id)->toBe($manualLecturer->id)->and($booking->status)->toBe('pending');

    $this->actingAs($this->admin)->get(route('admin.room-bookings.review'))->assertOk()->assertSee($purpose);
    $this->postJson(route('room-bookings.availability'), $slot)->assertOk()->assertJsonMissing(['id' => $room->id]);
    $this->post(route('admin.room-bookings.decision', $booking), ['decision' => 'approved'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($booking->fresh()->status)->toBe('approved');

    $this->actingAs($manualLecturer)->get(route('room-bookings.my'))->assertOk()->assertSee($purpose)
        ->assertViewHas('bookings', fn ($bookings) => $bookings->contains(fn ($row) => $row->is($booking) && $row->status === 'approved'));
});

test('manual Pelaksana borrowing eligibility respects environment and permission', function (string $environment, bool $grant) {
    $this->app->instance('env', $environment);
    $user = User::factory()->create(['auth_source' => 'manual', 'status' => 'active']);
    $user->assignRole('Pelaksana');
    if ($grant) {
        $user->givePermissionTo(['room_booking_read', 'room_booking_create']);
    }

    $this->actingAs($user)->get(route('room-bookings.index'))->assertForbidden();
    $this->get(route('room-bookings.create'))->assertForbidden();
    $this->get(route('room-bookings.my'))->assertForbidden();
})->with([['production', true], ['staging', false]]);

test('academic admin can access borrowing dashboard alongside review', function () {
    $response = $this->actingAs($this->admin)->get(route('room-bookings.index'));
    $response->assertOk()->assertSee('Dashboard Peminjaman Ruangan');
});

test('dashboard displays flash success message when redirected with session success', function () {
    $response = $this->actingAs($this->lecturer)
        ->withSession(['success' => 'Pengajuan berhasil dikirim dan menunggu persetujuan.'])
        ->get(route('room-bookings.index'));

    $response->assertOk();
    $response->assertSee('Pengajuan berhasil dikirim dan menunggu persetujuan.');
});

// =========================================================================
// 3. AJUKAN PEMINJAMAN RUANGAN (VALIDASI TANGGAL & PERIODE SEMESTER)
// =========================================================================

test('store rejects booking with start date in the past', function () {
    // Current time is travelTo 2026-11-19
    $response = $this->actingAs($this->lecturer)->post(route('room-bookings.store'), [
        'room_id' => $this->scenario['rooms']['a']->id,
        'start_at' => '2026-11-18T08:00', // Kemarin
        'end_at' => '2026-11-18T10:00',
        'purpose' => 'Uji tanggal lampau',
    ]);

    $response->assertSessionHasErrors(['start_at']);
    expect(RoomBookingRequest::where('purpose', 'Uji tanggal lampau')->count())->toBe(0);
});

test('checkAvailability rejects booking with start date in the past', function () {
    $response = $this->actingAs($this->lecturer)->postJson(route('room-bookings.availability'), [
        'start_at' => '2026-11-18T08:00',
        'end_at' => '2026-11-18T10:00',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Tanggal peminjaman tidak boleh di masa lampau.']);
});

test('store rejects booking outside the active academic period', function () {
    // Active period ends on 2027-01-31
    $response = $this->actingAs($this->lecturer)->post(route('room-bookings.store'), [
        'room_id' => $this->scenario['rooms']['a']->id,
        'start_at' => '2027-02-15T08:00', // Di luar semester aktif
        'end_at' => '2027-02-15T10:00',
        'purpose' => 'Uji di luar semester',
    ]);

    $response->assertSessionHasErrors(['start_at']);
    expect(RoomBookingRequest::where('purpose', 'Uji di luar semester')->count())->toBe(0);
});

test('store successfully creates booking and redirects to dashboard with success message (Opsi A)', function () {
    $response = $this->actingAs($this->lecturer)->post(route('room-bookings.store'), [
        'room_id' => $this->scenario['rooms']['b']->id,
        'start_at' => '2026-11-24T08:00',
        'end_at' => '2026-11-24T10:00',
        'purpose' => 'Pengajuan Kuliah Khusus',
    ]);

    $response->assertRedirect(route('room-bookings.index'));
    $response->assertSessionHas('success', 'Pengajuan berhasil dikirim dan menunggu persetujuan.');

    $created = RoomBookingRequest::where('purpose', 'Pengajuan Kuliah Khusus')->first();
    expect($created)->not->toBeNull();
    expect($created->status)->toBe('pending');
    expect($created->occurrences()->count())->toBe(1);
    expect($created->occurrences()->first()->usage_date->toDateString())->toBe('2026-11-24');
});

// =========================================================================
// 4. RIWAYAT PEMINJAMAN RUANGAN (ISOLASI & PEMBATALAN OLEH DOSEN)
// =========================================================================

test('lecturer only sees their own bookings in booking history', function () {
    // Booking lecturer 1
    $b1 = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['a']->id,
        'type' => 'single',
        'start_date' => '2026-11-25',
        'end_date' => '2026-11-25',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'purpose' => 'Rapat Dosen Budi',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $b1->occurrences()->create(['usage_date' => '2026-11-25', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);

    // Booking lecturer 2
    $b2 = RoomBookingRequest::create([
        'user_id' => $this->lecturer2->id,
        'room_id' => $this->scenario['rooms']['b']->id,
        'type' => 'single',
        'start_date' => '2026-11-25',
        'end_date' => '2026-11-25',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'purpose' => 'Konsultasi Dosen Siti',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $b2->occurrences()->create(['usage_date' => '2026-11-25', 'start_time' => '13:00:00', 'end_time' => '15:00:00']);

    // Lecturer 1 melihat riwayat
    $response = $this->actingAs($this->lecturer)->get(route('room-bookings.my'));
    $response->assertOk();
    $response->assertSee('Rapat Dosen Budi');
    $response->assertDontSee('Konsultasi Dosen Siti');

    // Lecturer 2 melihat riwayat
    $response2 = $this->actingAs($this->lecturer2)->get(route('room-bookings.my'));
    $response2->assertOk();
    $response2->assertSee('Konsultasi Dosen Siti');
    $response2->assertDontSee('Rapat Dosen Budi');
});

test('lecturer can cancel their own pending booking but cannot cancel other lecturers booking', function () {
    $booking1 = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['a']->id,
        'type' => 'single',
        'start_date' => '2026-11-26',
        'end_date' => '2026-11-26',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'purpose' => 'Batal Sendiri',
        'participants' => 0,
        'status' => 'pending',
        'booking_origin' => 'ordinary',
    ]);
    $booking1->occurrences()->create(['usage_date' => '2026-11-26', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);

    // Lecturer 2 mencoba membatalkan booking lecturer 1 -> 403
    $this->actingAs($this->lecturer2)->post(route('room-bookings.cancel', $booking1))->assertForbidden();
    expect($booking1->fresh()->status)->toBe('pending');

    // Lecturer 1 membatalkan booking miliknya sendiri -> sukses
    $this->actingAs($this->lecturer)->post(route('room-bookings.cancel', $booking1))->assertRedirect();
    expect($booking1->fresh()->status)->toBe('cancelled');
});
