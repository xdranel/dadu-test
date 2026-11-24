<?php

use App\Models\RoomBookingRequest;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\TaRoomScenario;

it('shows an actionable pending booking count to reviewers on the room booking menu', function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $scenario = TaRoomScenario::make();
    $read = Permission::findOrCreate('room_booking_read', 'web');
    $admin = User::factory()->create(['auth_source' => 'manual', 'status' => 'active']);
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $admin->givePermissionTo([$read, 'room_booking_review']);

    $createBooking = function (string $status, string $date, string $end, array $extra = []) use ($scenario) {
        $booking = RoomBookingRequest::create($extra + [
            'user_id' => $scenario['owner']->id,
            'room_id' => $scenario['rooms']['a']->id,
            'type' => 'single',
            'booking_origin' => 'ordinary',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '07:00:00',
            'end_time' => $end,
            'purpose' => 'Peminjaman uji notifikasi',
            'participants' => 1,
            'status' => $status,
        ]);
        $booking->occurrences()->create(['usage_date' => $date, 'start_time' => '07:00:00', 'end_time' => $end]);

        return $booking;
    };

    $createBooking('pending', '2026-11-19', '08:59:00');
    $ordinary = $createBooking('pending', '2026-11-19', '10:00:00');
    $createBooking('approved', '2026-11-20', '10:00:00');
    $createBooking('pending', '2026-11-20', '10:00:00', ['sidang_ta_registration_id' => $scenario['registration']->id]);

    $this->actingAs($admin)->get(route('room-bookings.index'))->assertOk()
        ->assertSee('Review Peminjaman Ruangan')
        ->assertSee('aria-label="1 pengajuan menunggu persetujuan"', false)
        ->assertDontSee('aria-label="2 pengajuan menunggu persetujuan"', false);

    $admin->givePermissionTo('sidang_ta_registration_review');
    $this->get(route('room-bookings.index'))->assertOk()
        ->assertSee('aria-label="2 pengajuan menunggu persetujuan"', false);

    $ordinary->update(['status' => 'approved']);
    $this->get(route('room-bookings.index'))->assertOk()
        ->assertSee('aria-label="1 pengajuan menunggu persetujuan"', false);

    $borrower = User::factory()->create(['auth_source' => 'sso', 'status' => 'active']);
    $borrower->assignRole(Role::findOrCreate('Pelaksana', 'web'));
    $borrower->givePermissionTo($read);
    $this->actingAs($borrower)->get(route('room-bookings.index'))->assertOk()
        ->assertDontSee('Review Peminjaman Ruangan')->assertDontSee('pengajuan menunggu persetujuan');
});
