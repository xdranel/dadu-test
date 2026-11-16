<?php

use App\Models\RoomBookingRequest;
use App\Services\RoomBookingTransitionService;
use App\Services\SidangTaBookingAdministration;
use App\Services\SidangTaBookingService;
use App\Services\SidangTaRegistrationReview;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->s = TaRoomScenario::make();
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $p = app(SidangTaBookingService::class)->preview($this->s['owner'], $this->s['registration'], '2026-11-23');
    $this->c = app(SidangTaBookingService::class)->confirm($this->s['owner'], $this->s['registration'], $p['preview_token']);
});
test('lifecycle111_withdraw_approval_cancels_only_linked_and_preserves_documents', function () {
    $s = $this->s;
    $s['registration']->documents()->create(['document_type' => 'ta_capture', 'file_path' => 'dummy.pdf', 'file_name' => 'dummy.pdf', 'file_type' => 'pdf', 'file_size' => 100, 'status' => 'verified', 'notes' => 'Sesuai']);
    $before = $s['registration']->documents()->get()->map->getRawOriginal()->all();
    $other = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['b']->id, 'type' => 'single', 'participants' => 1, 'purpose' => 'Unrelated', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'approved']);
    app(SidangTaRegistrationReview::class)->cancelApproval($s['reviewer'], $s['registration'], $s['registration']->fresh()->lock_version);
    expect($this->c->booking->fresh()->status)->toBe('cancelled')->and($this->c->fresh()->state)->toBe('cancelled')->and($this->c->fresh()->active_student_number)->toBeNull()->and($other->fresh()->status)->toBe('approved')->and($s['registration']->documents()->get()->map->getRawOriginal()->all())->toBe($before);
    expect(fn () => app(RoomBookingTransitionService::class)->decide($s['reviewer'], $this->c->booking->fresh(), 'approved', 'tab lama'))->toThrow(HttpException::class);
});
test('lifecycle111_admin_move_is_atomic_and_student_cannot_mutate', function () {
    $s = $this->s;
    $admin = app(SidangTaBookingAdministration::class);
    $slot = ['date' => '2026-11-24', 'room_id' => $s['rooms']['b']->id, 'start_time' => '09:00', 'end_time' => '11:00'];
    expect(fn () => $admin->reschedule($s['owner'], $s['registration'], $slot, $this->c->lock_version, 'Ganti'))->toThrow(HttpException::class);
    $before = $this->c->booking->getRawOriginal();
    expect(fn () => $admin->reschedule($s['reviewer'], $s['registration'], array_replace($slot, ['room_id' => $s['rooms']['lab']->id]), $this->c->lock_version, 'Ganti'))->toThrow(ValidationException::class);
    expect($this->c->booking->fresh()->getRawOriginal())->toBe($before);
    $c = $admin->reschedule($s['reviewer'], $s['registration'], $slot, $this->c->lock_version, 'Permintaan SSC');
    expect($c->booking->room_id)->toBe($s['rooms']['b']->id)->and($c->booking->occurrences->first()->usage_date->toDateString())->toBe('2026-11-24')->and($c->booking->histories()->count())->toBe(2);
    $c = $admin->cancel($s['reviewer'], $s['registration'], $c->lock_version, 'Batal oleh SSC');
    expect($c->booking->status)->toBe('cancelled')->and($c->active_student_number)->toBeNull();
});

test('lifecycle111_ssc_can_assign_replacement_after_cancellation_without_restoring_student_control', function () {
    $s = $this->s;
    $admin = app(SidangTaBookingAdministration::class);
    $old = $this->c->booking;
    $c = $admin->cancel($s['reviewer'], $s['registration'], $this->c->lock_version, 'Batal awal');
    $c = $admin->reschedule($s['reviewer'], $s['registration'], ['date' => '2026-11-24', 'room_id' => $s['rooms']['b']->id, 'start_time' => '09:00', 'end_time' => '11:00'], $c->lock_version, 'Pengganti oleh SSC');
    expect($old->fresh()->status)->toBe('cancelled')->and($c->booking->id)->not->toBe($old->id)->and($c->booking->status)->toBe('approved')->and($c->was_confirmed)->toBeTrue();
});

it('review111_withdrawal_releases_waiting_lecturer_context_without_booking', function () {
    $s = $this->s;
    // Use a separate approved registration that has never received a room booking.
    $reg = $s['registration']->replicate();
    $p = $s['period']->replicate();
    $p->name = 'Periode kedua';
    $p->save();
    $reg->sidang_ta_period_id = $p->id;
    $reg->save();
    app(SidangTaBookingAdministration::class)->cancel($s['reviewer'], $s['registration'], $this->c->lock_version, 'Selesaikan booking awal');
    $c = app(SidangTaBookingAdministration::class)->markLecturer($s['reviewer'], $reg, 0, 'Dosen akan mengatur');
    app(SidangTaRegistrationReview::class)->cancelApproval($s['reviewer'], $reg, $reg->fresh()->lock_version);
    expect($c->fresh()->active_student_number)->toBeNull()->and($c->fresh()->state)->toBe('cancelled')->and($c->fresh()->mode)->toBe('lecturer');
});
it('review111_old_room_endpoint_cannot_cancel_linked_ta_after_start', function () {
    $s = $this->s;
    $this->travelTo(Carbon::parse('2026-11-23 08:30:00', 'Asia/Jakarta'));
    $this->actingAs($s['reviewer'])->post(route('admin.room-bookings.cancel', $this->c->booking), ['reason' => 'Tab lama'])->assertSessionHasErrors('schedule');
    expect($this->c->booking->fresh()->status)->toBe('approved')->and($this->c->fresh()->state)->toBe('confirmed');
});
