<?php

use App\Models\AcademicPeriod;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\RoomScheduleSource;
use App\Models\Unit;
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

test('academic admin reviews booking cards with room, program and date without the import notice', function () {
    $room = $this->scenario['rooms']['a'];
    $room->update(['room_code' => 'KTT-REVIEW-01']);
    Lecturer::create(['name' => 'Dosen Uji', 'email_domain' => $this->lecturer->email,
        'nip_ypt' => 'NIP-UJI-01', 'lecturer_code' => 'ABC', 'study_program' => 'S1 Informatika']);
    $booking = RoomBookingRequest::create([
        'user_id' => $this->lecturer->id,
        'room_id' => $this->scenario['rooms']['a']->id,
        'type' => 'single',
        'start_date' => '2026-11-25',
        'end_date' => '2026-11-25',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'purpose' => 'Rapat Koordinasi Dosen',
        'admin_note' => 'Konfirmasi agenda dengan peminjam.',
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
    $response->assertDontSee('Import jadwal iGracias')->assertSee('Catatan admin')
        ->assertSee('S1 Informatika')->assertDontSee('NIP-UJI-01')->assertSee('Rabu, 25-Nov-2026')
        ->assertSee(route('admin.room-bookings.history'))
        ->assertSee('Konfirmasi agenda dengan peminjam.')
        ->assertDontSee('Kalender Ketersediaan Ruangan')->assertDontSee('Rincian Penggunaan Ruangan');
});

test('review keeps ongoing multi-month bookings active and archives by the last use month', function () {
    $room = $this->scenario['rooms']['a'];
    $otherRoom = $this->scenario['rooms']['b'];
    $room->update(['room_code' => 'KTT A-1.04']);
    $otherRoom->update(['room_code' => 'KTT2.37']);
    $unit = Unit::create(['name' => 'Layanan Akademik']);
    $this->admin->update(['unit_id' => $unit->id]);
    $make = function (string $purpose, array $dates, string $status, ?Room $bookingRoom = null) use ($room) {
        $booking = RoomBookingRequest::create([
            'user_id' => $this->admin->id, 'room_id' => ($bookingRoom ?? $room)->id, 'type' => 'recurring',
            'start_date' => $dates[0], 'end_date' => end($dates), 'start_time' => '07:00:00', 'end_time' => '08:00:00',
            'purpose' => $purpose, 'participants' => 2, 'status' => $status,
        ]);
        foreach ($dates as $date) {
            $booking->occurrences()->create(['usage_date' => $date, 'start_time' => '07:00:00', 'end_time' => '08:00:00']);
        }

        return $booking;
    };
    $previous = $make('Arsip Oktober', ['2026-10-25'], 'rejected');
    $otherPrevious = $make('Arsip ruangan lain', ['2026-10-24'], 'approved', $otherRoom);
    $finished = $make('Selesai pagi ini', ['2026-11-19'], 'approved');
    $ongoing = $make('Peminjaman lintas bulan', ['2026-11-18', '2026-12-02'], 'approved');
    $newest = $make('Pengajuan terbaru', ['2026-11-20'], 'pending', $otherRoom);

    $active = $this->actingAs($this->admin)->get(route('admin.room-bookings.review'))->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$newest->id, $ongoing->id])
        ->assertSee('Layanan Akademik')->assertSee('Batalkan Peminjaman')->assertSee('Konfirmasi pembatalan')
        ->assertSee('class="btn btn-primary" aria-haspopup="dialog"', false)
        ->assertSeeInOrder(['Peminjaman lintas bulan', 'Rabu, 18-Nov-2026', 'Rabu, 02-Des-2026', '07:00–08:00 WIB']);
    expect(substr_count($active->getContent(), '07:00–08:00 WIB'))->toBe(2);
    $this->get(route('admin.room-bookings.review', ['room_code' => 'a104']))->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$ongoing->id])
        ->assertSee('name="room_code"', false)->assertSee('value="a104"', false);

    $history = '/dashboard/admin/room-bookings/history';
    $this->get($history)->assertOk()->assertViewHas('month', '2026-11')
        ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$finished->id])
        ->assertDontSee('Batalkan Peminjaman');
    $this->get($history.'?month=2026-10')->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$otherPrevious->id, $previous->id]);
    $this->get($history.'?month=2026-10&room_code=ktt2.37')->assertOk()->assertViewHas('month', '2026-10')
        ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$otherPrevious->id])
        ->assertSee('value="ktt2.37"', false)->assertSee($history.'?month=2026-10');
    $this->actingAs($this->lecturer)->get($history)->assertForbidden();
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
