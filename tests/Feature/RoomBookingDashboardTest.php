<?php

use App\Models\RoomBookingRequest;
use App\Models\Room;
use App\Models\RoomWeeklyPattern;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'Asia/Jakarta'));
    $this->scenario = TaRoomScenario::make();
    $this->actor = User::factory()->create(['auth_source' => 'sso', 'status' => 'active']);
    $this->actor->assignRole(Role::findOrCreate('Pelaksana', 'web'));
    $this->actor->givePermissionTo(Permission::findOrCreate('room_booking_read', 'web'));
    $this->scenario['rooms']['a']->update(['room_code' => 'KTT1.02', 'building' => 'TUS-GEDUNG A']);
    $this->scenario['rooms']['b']->update(['room_code' => 'KTT2.02', 'building' => 'TUS-GEDUNG A']);
    $this->scenario['rooms']['lab']->update(['label' => 'LAB']);
});

it('keeps unavailable classrooms inspectable and never offers bookable time for them', function () {
    $room = $this->scenario['rooms']['a'];
    $room->update(['status' => 'Tidak Dapat Dipakai']);
    $this->actingAs($this->actor)->get(route('room-bookings.index'))->assertOk()
        ->assertSee('data-room-code="KTT1.02"', false)
        ->assertViewHas('roomStates', fn ($states) => $states[$room->id] === 'inactive');
    $this->getJson(route('room-bookings.calendar.detail', ['date' => '2026-11-24', 'room_id' => $room->id]))->assertOk()
        ->assertJsonPath('room.status', 'Tidak Dapat Dipakai')->assertJsonCount(0, 'available_slots');
    $this->getJson(route('room-bookings.calendar.detail', ['date' => '2026-11-24', 'room_id' => $this->scenario['rooms']['lab']->id]))
        ->assertNotFound();
});

it('defaults to today and offers dates from the navigated month even without a schedule source', function () {
    $this->scenario['source']->delete();
    $this->actingAs($this->actor)->get(route('room-bookings.index'))->assertOk()
        ->assertViewHas('selectedDate', '2026-11-24')
        ->assertSee('data-calendar-date="2026-11-24"', false)
        ->assertSee(route('room-bookings.index', ['month' => '2026-10']))
        ->assertDontSee('Daftar Peminjaman Ruangan');

    $this->get(route('room-bookings.index', ['month' => '2026-10', 'date' => '2026-11-24']))->assertOk()
        ->assertViewHas('selectedDate', '2026-10-01')->assertViewHas('calendar', fn ($dates) => count($dates) === 31)
        ->assertSee('data-calendar-date="2026-10-31"', false)->assertDontSee('data-calendar-date="2026-11-24"', false);

    $this->get(route('room-bookings.index', ['month' => '2026-10', 'date' => '2026-10-12']))->assertOk()
        ->assertViewHas('selectedDate', '2026-10-12');
});

it('filters clickable rooms by partial normalized code, building and mapped floor', function () {
    $this->actingAs($this->actor)->get(route('room-bookings.index', ['search' => 'ktt 1.', 'building' => 'TUS-GEDUNG A', 'floor' => '1']))
        ->assertOk()->assertSee('data-room-code="KTT1.02"', false)->assertDontSee('data-room-code="KTT2.02"', false)
        ->assertDontSee('data-room-code="LAB"', false);
    $this->get(route('room-bookings.index', ['floor' => '2']))->assertOk()
        ->assertSee('data-room-code="KTT2.02"', false)->assertDontSee('data-room-code="KTT1.02"', false);
});

it('shows the workbook room A-1.04 on floor two and omits obsolete rooms outside the floor plan', function () {
    $attributes = ['name' => '', 'capacity' => 40, 'label' => 'KELAS', 'status' => 'Dapat Dipakai'];
    Room::create(['room_code' => 'KTT A-1.04', 'building' => 'TUS-GEDUNG A'] + $attributes);
    Room::create(['room_code' => 'KELAS-LAMA', 'building' => 'Gedung Lama'] + $attributes);

    $this->actingAs($this->actor)->get(route('room-bookings.index'))->assertOk()
        ->assertSee('data-room-code="KTT A-1.04"', false)
        ->assertDontSee('Ruangan di luar denah')->assertDontSee('KELAS-LAMA')->assertDontSee('Gedung Lama');
    $this->get(route('room-bookings.index', ['search' => 'A-1.04', 'floor' => '2']))->assertOk()
        ->assertSee('Gedung A · Lantai 2')->assertSee('data-room-code="KTT A-1.04"', false);
    $this->get(route('room-bookings.index', ['search' => 'A-1.04', 'floor' => '1']))->assertOk()
        ->assertDontSee('data-room-code="KTT A-1.04"', false);
});

it('shows only the selected room and date in the calendar detail response', function () {
    $scenario = $this->scenario;
    foreach (['a', 'b'] as $key) {
        $room = $scenario['rooms'][$key];
        RoomWeeklyPattern::create(['academic_period_id' => $scenario['academic']->id, 'room_id' => $room->id,
            'weekday' => 2, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
            'reference_date' => '2026-11-24', 'source_identity' => $key]);
        $booking = RoomBookingRequest::create(['user_id' => $this->actor->id, 'room_id' => $room->id,
            'type' => 'single', 'start_date' => '2026-11-24', 'end_date' => '2026-11-25',
            'start_time' => '11:00:00', 'end_time' => '12:00:00', 'purpose' => 'Peminjaman '.$key,
            'participants' => 1, 'status' => 'approved']);
        $booking->occurrences()->create(['usage_date' => '2026-11-24', 'start_time' => '11:00:00', 'end_time' => '12:00:00']);
        $booking->occurrences()->create(['usage_date' => '2026-11-25', 'start_time' => '15:00:00', 'end_time' => '16:00:00']);
    }

    $room = $scenario['rooms']['a'];
    $url = route('room-bookings.calendar.detail', ['date' => '2026-11-24', 'room_id' => $room->id]);
    $this->actingAs($this->actor)->getJson($url)->assertOk()
        ->assertJsonPath('room.room_code', 'KTT1.02')->assertJsonPath('date', '2026-11-24')
        ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.room_id', $room->id)
        ->assertJsonCount(1, 'bookings')->assertJsonPath('bookings.0.purpose', 'Peminjaman a')
        ->assertJsonPath('bookings.0.start_time', '11:00')->assertJsonCount(1, 'available_slots')
        ->assertJsonPath('available_slots.0.free_slots.1', '09:00 - 11:00 WIB');
    $this->getJson(route('room-bookings.calendar.detail', ['date' => '2026-11-24']))->assertOk()
        ->assertJsonMissingPath('room')->assertJsonCount(2, 'bookings');
    $this->get(route('room-bookings.index'))->assertOk()->assertViewHas('roomStates', fn ($states) => $states[$room->id] === 'booked');
    $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
});
