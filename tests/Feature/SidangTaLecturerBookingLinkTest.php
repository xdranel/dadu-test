<?php

use App\Models\RoomBookingRequest;
use App\Models\User;
use App\Services\RoomBookingTransitionService;
use App\Services\SidangTaBookingAdministration;
use App\Services\SidangTaBookingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->s = TaRoomScenario::make();
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
});
function lecturerBooking111($s, $status = 'pending')
{
    return tap(RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'participants' => 5, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00', 'purpose' => 'Pesanan dosen', 'status' => $status]), fn ($b) => $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']));
}
test('link111_mark_before_lecturer_booking_invalidates_old_preview', function () {
    $s = $this->s;
    $p = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $c = app(SidangTaBookingAdministration::class)->markLecturer($s['reviewer'], $s['registration'], 0, 'Jadwal melalui dosen');
    expect($c->mode)->toBe('lecturer')->and($c->active_student_number)->toBe($s['student']->student_number)->and(RoomBookingRequest::count())->toBe(0);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']))->toThrow(HttpException::class);
});
test('link111_pending_link_never_approves_or_changes_owner', function ($status) {
    $s = $this->s;
    $service = app(SidangTaBookingAdministration::class);
    $c = $service->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen mengatur');
    $b = lecturerBooking111($s, $status);
    $c = $service->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Tautkan booking dosen');
    expect($c->state)->toBe($status === 'pending' ? 'pending_lecturer' : 'confirmed')->and($b->fresh()->status)->toBe($status)->and($b->fresh()->user_id)->toBe($s['reviewer']->id)->and($b->occurrences()->count())->toBe(1);
    if ($status === 'pending') {
        app(RoomBookingTransitionService::class)->decide($s['reviewer'], $b->fresh(), 'approved', 'Sesuai');
        expect($c->fresh()->state)->toBe('confirmed')->and($c->fresh()->was_confirmed)->toBeTrue();
    }
})->with(['pending', 'approved']);
test('link111_invalid_booking_and_double_binding_are_rejected', function ($change) {
    $s = $this->s;
    $service = app(SidangTaBookingAdministration::class);
    $c = $service->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen');
    $b = lecturerBooking111($s);
    if ($change === 'inactive') {
        $s['rooms']['lab']->update(['status' => 'Tidak Dapat Dipakai']);
        $b->update(['room_id' => $s['rooms']['lab']->id]);
    }
    if ($change === 'rejected') {
        $b->update(['status' => 'rejected']);
    }
    if ($change === 'duration') {
        $b->occurrences()->update(['end_time' => '09:00']);
    }
    if ($change === 'multiple') {
        $b->occurrences()->create(['usage_date' => '2026-11-24', 'start_time' => '08:00', 'end_time' => '10:00']);
    }
    expect(fn () => $service->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Tautkan'))->toThrow(ValidationException::class);
    expect($b->fresh()->sidang_ta_registration_id)->toBeNull();
})->with(['inactive', 'rejected', 'duration', 'multiple']);
test('link111_active_automatic_requires_explicit_cancellation_first', function () {
    $s = $this->s;
    $p = app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
    $c = app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']);
    expect(fn () => app(SidangTaBookingAdministration::class)->markLecturer($s['reviewer'], $s['registration'], $c->lock_version, 'Dosen'))->toThrow(ValidationException::class);
});

test('link111_lecturer_owner_can_cancel_linked_pending_without_reopening_student_booking', function () {
    $s = $this->s;
    $actor = User::factory()->create(['email' => 'krisna@dosen.test', 'status' => 'active', 'auth_source' => 'manual']);
    $role = Role::findOrCreate('Pelaksana', 'web');
    $role->syncPermissions([]);
    $actor->assignRole($role);
    $actor->givePermissionTo(Permission::findOrCreate('room_booking_update', 'web'));
    $service = app(SidangTaBookingAdministration::class);
    $c = $service->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen');
    $b = lecturerBooking111($s);
    $b->update(['user_id' => $actor->id]);
    $c = $service->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Booking dosen');
    app(RoomBookingTransitionService::class)->cancel($actor, $b->fresh(), 'Dosen berhalangan');
    expect($c->fresh()->state)->toBe('cancelled')->and($c->fresh()->mode)->toBe('lecturer')->and($c->fresh()->active_student_number)->toBeNull();
    expect(fn () => app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23'))->toThrow(ValidationException::class);
});
test('link111_ssc_can_cancel_a_waiting_lecturer_context_to_release_the_student', function () {
    $s = $this->s;
    $service = app(SidangTaBookingAdministration::class);
    $c = $service->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen');
    $c = $service->cancel($s['reviewer'], $s['registration'], $c->lock_version, 'Belum jadi dijadwalkan');
    expect($c->active_student_number)->toBeNull()->and($c->mode)->toBe('lecturer')->and($c->state)->toBe('cancelled')->and(RoomBookingRequest::count())->toBe(0);
});
