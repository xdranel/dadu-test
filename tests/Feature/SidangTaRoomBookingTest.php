<?php

use App\Models\RoomBookingRequest;
use App\Models\RoomWeeklyPattern;
use App\Models\SidangTaSchedule;
use App\Services\SidangTaBookingService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->s = TaRoomScenario::make();
    $this->travelTo(now()->setDate(2026, 11, 19)->setTime(9, 0));
});
function preview111($s)
{
    return app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-23');
}
test('booking111_preview_earliest_across_classrooms_without_creating_hold', function () {
    $s = $this->s;
    RoomWeeklyPattern::create(['academic_period_id' => $s['academic']->id, 'room_id' => $s['rooms']['a']->id, 'weekday' => 1, 'reference_date' => '2026-11-09', 'start_time' => '08:00', 'end_time' => '09:00', 'source_identity' => str_repeat('a', 64)]);
    $p = preview111($s);
    expect($p['candidate']['room_id'])->toBe($s['rooms']['b']->id)->and($p['candidate']['start_time'])->toBe('08:00:00')->and($p['candidate']['end_time'])->toBe('10:00:00')->and($p['identity']['student_number'])->toBe($s['student']->student_number)->and(RoomBookingRequest::count())->toBe(0)->and(SidangTaSchedule::count())->toBe(0);
});
test('booking111_confirm_creates_one_approved_booking_and_preserves_application', function () {
    $s = $this->s;
    $before = $s['registration']->fresh()->getRawOriginal();
    $p = preview111($s);
    $c = app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']);
    expect($c->booking->status)->toBe('approved')->and($c->was_confirmed)->toBeTrue()->and($c->booking->occurrences)->toHaveCount(1)->and($s['registration']->fresh()->getRawOriginal())->toBe($before);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']))->toThrow(HttpException::class);
    expect(RoomBookingRequest::count())->toBe(1);
});
test('booking111_nonapproved_cannot_search', function ($status) {
    $s = $this->s;
    $s['registration']->update(['status' => $status]);
    expect(fn () => preview111($s))->toThrow(ValidationException::class);
})->with(['submitted', 'needs_review', 'rejected']);
test('booking111_unverified_source_or_outside_day_does_not_offer_slot', function () {
    $s = $this->s;
    $s['source']->update(['is_complete' => false]);
    expect(preview111($s)['candidate'])->toBeNull();
    expect(fn () => app(SidangTaBookingService::class)->preview($s['owner'], $s['registration'], '2026-11-22'))->toThrow(ValidationException::class);
});
test('booking111_context_setting_source_changes_invalidate_preview', function ($change) {
    $s = $this->s;
    $p = preview111($s);
    if ($change === 'source') {
        $s['source']->increment('version');
    }
    if ($change === 'setting') {
        $s['settings']->increment('lock_version');
    }
    if ($change === 'mode') {
        SidangTaSchedule::create(['sidang_ta_registration_id' => $s['registration']->id, 'student_number' => $s['student']->student_number, 'mode' => 'lecturer', 'state' => 'awaiting_lecturer', 'lock_version' => 2]);
    }
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']))->toThrow(HttpException::class);
    expect(RoomBookingRequest::count())->toBe(0);
})->with(['source', 'setting', 'mode']);
test('booking111_taken_candidate_requires_new_preview_not_silent_room_change', function () {
    $s = $this->s;
    $p = preview111($s);
    $b = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $p['candidate']['room_id'], 'purpose' => 'Other', 'type' => 'single', 'participants' => 1, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'pending']);
    $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token']))->toThrow(HttpException::class);
    expect(RoomBookingRequest::count())->toBe(1);
});
test('booking111_other_user_or_tampered_token_cannot_confirm', function () {
    $s = $this->s;
    $p = preview111($s);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['reviewer'], $s['registration'], $p['preview_token']))->toThrow(HttpException::class);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $p['preview_token'].'x'))->toThrow(ValidationException::class);
});

test('booking111_earliest_tie_uses_room_code_before_id', function () {
    $s = $this->s;
    $s['rooms']['a']->update(['room_code' => 'ZZZ']);
    $s['rooms']['b']->update(['room_code' => 'AAA']);
    expect(preview111($s)['candidate']['room_id'])->toBe($s['rooms']['b']->id);
});
test('booking111_invalid_date_produces_validation_error', function () {
    expect(fn () => app(SidangTaBookingService::class)->preview($this->s['owner'], $this->s['registration'], 'not-a-date'))->toThrow(ValidationException::class);
});
it('review111_latest_approval_is_the_only_new_student_booking_context', function () {
    $s = $this->s;
    $preview = preview111($s);
    $p = $s['period']->replicate();
    $p->name = 'Periode terbaru belum diatur';
    $p->save();
    $new = $s['registration']->replicate();
    $new->fill(['sidang_ta_period_id' => $p->id, 'submitted_at' => '2026-11-18 09:00:00']);
    $new->save();
    expect(fn () => preview111($s))->toThrow(ValidationException::class);
    expect(fn () => app(SidangTaBookingService::class)->confirm($s['owner'], $s['registration'], $preview['preview_token']))->toThrow(HttpException::class);
    $this->actingAs($s['owner'])->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee(route('sidang-ta-bookings.index', $s['registration']))
        ->assertDontSee(route('sidang-ta-bookings.index', $new));
    $this->get(route('sidang-ta-execution.index'))->assertOk()->assertDontSee('Cari jadwal');
});
