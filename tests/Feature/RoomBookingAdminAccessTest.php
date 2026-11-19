<?php

use App\Models\RoomBookingRequest;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    foreach (['room_booking_read', 'room_booking_create', 'room_booking_update', 'room_booking_review'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
});

function room127Admin(string $role = 'admin', array $permissions = ['room_booking_read', 'room_booking_create', 'room_booking_update']): User
{
    $actor = User::factory()->create(['auth_source' => 'manual', 'status' => 'active']);
    $actor->assignRole(Role::findOrCreate($role, 'web'));
    $actor->givePermissionTo($permissions);
    return $actor;
}

function room127Booking(array $s, User $owner, string $status = 'pending', string $purpose = 'Pengajuan uji admin'): RoomBookingRequest
{
    $booking = RoomBookingRequest::create(['user_id' => $owner->id, 'room_id' => $s['rooms']['a']->id,
        'type' => 'single', 'purpose' => $purpose, 'participants' => 1, 'status' => $status,
        'booking_origin' => 'ordinary', 'start_date' => '2026-11-24', 'end_date' => '2026-11-24',
        'start_time' => '10:00', 'end_time' => '11:00']);
    $booking->occurrences()->create(['usage_date' => '2026-11-24', 'start_time' => '10:00', 'end_time' => '11:00']);
    return $booking;
}

test('authorized admin can see borrowing submenus and submit an ordinary pending booking', function (string $role) {
    $actor = room127Admin($role);
    $this->actingAs($actor)->get(route('room-bookings.index'))->assertOk()
        ->assertSee('Pengajuan Peminjaman Ruangan')->assertSee('Daftar Peminjaman Saya');
    $this->get(route('room-bookings.create'))->assertOk();
    $this->get(route('room-bookings.my'))->assertOk();
    $slot = ['start_at' => '2026-11-24T10:00', 'end_at' => '2026-11-24T11:00'];
    $this->postJson(route('room-bookings.availability'), $slot)->assertOk()->assertJsonFragment(['id' => $this->s['rooms']['a']->id]);
    $this->post(route('room-bookings.store'), $slot + ['room_id' => $this->s['rooms']['a']->id, 'purpose' => 'Rapat admin127'])
        ->assertSessionHasNoErrors()->assertRedirect(route('room-bookings.index'));
    $booking = RoomBookingRequest::where('purpose', 'Rapat admin127')->sole();
    expect($booking->user_id)->toBe($actor->id)->and($booking->status)->toBe('pending')
        ->and($booking->booking_origin)->toBe('ordinary')->and($booking->occurrences()->count())->toBe(1);
})->with(['admin', 'Akademik']);

test('admin without review permission can only cancel their own pending ordinary booking', function () {
    $actor = room127Admin();
    $other = room127Admin();
    $own = room127Booking($this->s, $actor);
    $foreign = room127Booking($this->s, $other, 'pending', 'Milik admin lain127');
    $approved = room127Booking($this->s, $actor, 'approved', 'Sudah disetujui127');
    $this->actingAs($actor)->get(route('room-bookings.my'))->assertOk()
        ->assertSee('Pengajuan uji admin')->assertDontSee('Milik admin lain127');
    $this->post(route('room-bookings.cancel', $foreign))->assertForbidden();
    $this->post(route('room-bookings.cancel', $approved))->assertStatus(422);
    $this->post(route('room-bookings.cancel', $own))->assertRedirect();
    expect($own->fresh()->status)->toBe('cancelled')->and($foreign->fresh()->status)->toBe('pending')
        ->and($approved->fresh()->status)->toBe('approved');
    expect($own->histories()->latest('id')->first()->actor_id)->toBe($actor->id);
    $this->get(route('admin.room-bookings.review'))->assertForbidden();
});

test('admin role does not bypass create permission and does not grant room review', function () {
    $actor = room127Admin('admin', ['room_booking_read']);
    $this->actingAs($actor)->get(route('room-bookings.index'))->assertOk()
        ->assertDontSee('Pengajuan Peminjaman Ruangan')->assertDontSee('Review Peminjaman Ruangan');
    $this->get(route('room-bookings.create'))->assertForbidden();
    $this->postJson(route('room-bookings.availability'), [])->assertForbidden();
    $this->post(route('room-bookings.store'), [])->assertForbidden();
    $this->get(route('admin.room-bookings.review'))->assertForbidden();
});

test('authorized ordinary admin booking still rejects an occupied slot', function () {
    $actor = room127Admin();
    room127Booking($this->s, $this->s['owner']);
    $this->actingAs($actor)->post(route('room-bookings.store'), [
        'room_id' => $this->s['rooms']['a']->id, 'start_at' => '2026-11-24T10:15',
        'end_at' => '2026-11-24T10:45', 'purpose' => 'Bentrok admin127',
    ])->assertSessionHasErrors('availability');
    expect(RoomBookingRequest::count())->toBe(1);
});
