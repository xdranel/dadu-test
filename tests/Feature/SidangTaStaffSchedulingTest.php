<?php

use App\Models\RoomBookingRequest;
use App\Models\User;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->s['settings']->update(['duration_minutes' => 90]);
});

function staffSchedulingBlock(array $s, array $candidate): RoomBookingRequest
{
    $b = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $candidate['room_id'],
        'type' => 'single', 'start_date' => $candidate['date'], 'end_date' => $candidate['date'],
        'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time'],
        'purpose' => 'Peminjaman lain', 'participants' => 1, 'status' => 'pending', 'booking_origin' => 'ordinary']);
    $b->occurrences()->create(['usage_date' => $candidate['date'], 'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time']]);

    return $b;
}

test('staff searches the exact interval and recommends Gedung A before an imported Gedung B class', function () {
    $s = $this->s;
    $s['rooms']['a']->update(['building' => 'TUS-GEDUNG A']);
    $s['rooms']['b']->update(['building' => 'TUS-GEDUNG B']);
    $s['rooms']['lab']->update(['label' => 'LAB']);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number,
        'preferred_date' => '2026-11-23', 'preferred_start_time' => '12:00', 'preferred_end_time' => '12:45', 'preferred_room_id' => $s['rooms']['b']->id]);
    $p = app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23', '12:00', '12:45');
    expect($p['options'][0]['candidate']['room_id'])->toBe($s['rooms']['a']->id)
        ->and(substr($p['options'][0]['candidate']['start_time'], 0, 5))->toBe('12:00')
        ->and($p['options'][0]['recommended'])->toBeTrue();
    expect(collect($p['options'])->pluck('candidate.room_id')->unique()->sort()->values()->all())
        ->toBe(collect($s['rooms'])->except('lab')->pluck('id')->sort()->values()->all());
    foreach ($p['options'] as $option) {
        expect($option['candidate']['start_time'])->toBe('12:00:00')
            ->and($option['candidate']['end_time'])->toBe('12:45:00')
            ->and($option['candidate']['duration_minutes'])->toBe(45);
    }
    expect(RoomBookingRequest::count())->toBe(0);
});

test('staff can choose a non recommended slot and it is immediately approved with a frozen info snapshot', function () {
    $s = $this->s;
    $service = app(SidangTaStaffScheduling::class);
    $p = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '12:00', '14:00');
    $selected = collect($p['options'])->first(fn ($o) => $o['candidate']['room_id'] === $s['rooms']['b']->id && str_starts_with($o['candidate']['start_time'], '12:00'));
    expect($selected)->not->toBeNull();
    $c = $service->confirm($s['reviewer'], $s['registration'], $selected['token']);
    expect($c->booking->status)->toBe('approved')->and($c->booking->room_id)->toBe($s['rooms']['b']->id)
        ->and(substr($c->booking->start_time, 0, 5))->toBe('12:00')->and($c->details_snapshot)->not->toBeNull();
    expect($s['registration']->fresh()->status)->toBe('approved');
});

test('staff can move an existing booking into an explicit interval on a legacy period', function () {
    $s = $this->s;
    $s['settings']->update(['duration_minutes' => 120, 'break_start_time' => '12:00', 'break_end_time' => '13:00']);
    $service = app(SidangTaStaffScheduling::class);
    $original = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0];
    $before = $service->confirm($s['reviewer'], $s['registration'], $original['token']);
    $preview = $service->preview($s['reviewer'], $s['registration'], '2026-11-24', '12:00', '13:45');
    $replacement = collect($preview['options'])->first(fn ($option) => $option['candidate']['start_time'] === '12:00:00');
    expect($replacement)->not->toBeNull();

    $after = $service->confirm($s['reviewer'], $s['registration'], $replacement['token']);
    $occurrence = $after->booking->occurrences->sole();
    expect($after->booking->id)->toBe($before->booking->id)
        ->and($occurrence->usage_date->toDateString())->toBe('2026-11-24')
        ->and(substr($occurrence->start_time, 0, 5))->toBe('12:00')
        ->and(substr($occurrence->end_time, 0, 5))->toBe('13:45')
        ->and($s['settings']->fresh()->duration_minutes)->toBe(120);
});

test('staff can assign a past imported lab only at the original slot and record the result', function () {
    $s = $this->s;
    $lab = $s['rooms']['lab'];
    $lab->update(['label' => 'LAB']);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number,
        'planning_source' => 'import', 'preferred_date' => '2026-11-23',
        'preferred_start_time' => '10:00:00', 'preferred_end_time' => '11:05:00', 'preferred_room_id' => $lab->id]);
    $this->travelTo(Carbon::parse('2026-11-30 09:00:00', 'Asia/Jakarta'));
    $service = app(SidangTaStaffScheduling::class);
    expect(fn () => $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '10:00', '11:30', true))->toThrow(ValidationException::class);
    $normal = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '10:00', '11:05');
    expect(collect($normal['options'])->pluck('candidate.room_id'))->not->toContain($lab->id);
    $imported = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '10:00', '11:05', true);
    expect($imported['options'])->toHaveCount(1)->and($imported['options'][0]['candidate']['room_id'])->toBe($lab->id);
    $context = $service->confirm($s['reviewer'], $s['registration'], $imported['options'][0]['token']);
    expect($context->state)->toBe('completed')->and($context->active_student_number)->toBeNull()
        ->and($context->details_snapshot['room'])->toBe($lab->room_code);
    $context = app(\App\Services\SidangTaExecutionOutcomeService::class)->set($s['reviewer'], $s['registration'], 'passed', $context->lock_version);
    expect($context->result_status)->toBe('passed');
});

test('staff can search the past again to correct a confirmed room assignment', function () {
    $s = $this->s;
    $service = app(SidangTaStaffScheduling::class);
    $original = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30');
    $before = $service->confirm($s['reviewer'], $s['registration'], $original['options'][0]['token']);
    $this->travelTo(Carbon::parse('2026-11-30 09:00:00', 'Asia/Jakarta'));

    $search = $service->preview($s['reviewer'], $s['registration'], '2026-11-24', '10:15', '11:00');
    $after = $service->confirm($s['reviewer'], $s['registration'], $search['options'][0]['token']);
    expect($after->booking->id)->toBe($before->booking->id)
        ->and($after->state)->toBe('completed')
        ->and($after->details_snapshot['date'])->toBe('2026-11-24')
        ->and($after->details_snapshot['start_time'])->toBe('10:15')
        ->and($after->details_snapshot['end_time'])->toBe('11:00');
});

test('a room taken after preview rejects the chosen token without silently using another slot', function () {
    $s = $this->s;
    $service = app(SidangTaStaffScheduling::class);
    $option = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0];
    staffSchedulingBlock($s, $option['candidate']);
    expect(fn () => $service->confirm($s['reviewer'], $s['registration'], $option['token']))
        ->toThrow(HttpException::class);
    expect(RoomBookingRequest::where('booking_origin', 'sidang_ta')->count())->toBe(0);
});

test('student and a different admin cannot reuse a scheduling token and changed settings invalidate it', function () {
    $s = $this->s;
    $service = app(SidangTaStaffScheduling::class);
    expect(fn () => $service->preview($s['owner'], $s['registration'], '2026-11-23', '08:00', '09:30'))
        ->toThrow(HttpException::class);
    $token = $service->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0]['token'];
    $other = User::factory()->create(['status' => 'active']);
    $other->givePermissionTo('sidang_ta_registration_review');
    expect(fn () => $service->confirm($other, $s['registration'], $token))->toThrow(HttpException::class);
    $s['settings']->increment('lock_version');
    expect(fn () => $service->confirm($s['reviewer'], $s['registration'], $token))->toThrow(HttpException::class);
    expect(RoomBookingRequest::count())->toBe(0);
});

test('inactive execution does not offer slots', function () {
    $s = $this->s;
    $s['settings']->update(['is_active' => false]);
    expect(fn () => app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30'))
        ->toThrow(ValidationException::class);
});

test('a completed legacy execution can receive its late result and freeze historical identity', function () {
    $s = $this->s;
    $booking = staffSchedulingBlock($s, ['room_id' => $s['rooms']['a']->id, 'date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
    $booking->update(['status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $s['registration']->id]);
    $c = $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number,
        'room_booking_request_id' => $booking->id, 'was_confirmed' => true, 'state' => 'completed']);
    \App\Models\StudentFinalProject::create(['student_number' => $s['student']->student_number, 'thesis_title_id' => 'Judul saat sidang']);
    $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'Asia/Jakarta'));
    app(\App\Services\SidangTaExecutionOutcomeService::class)->set($s['reviewer'], $s['registration'], 'passed', $c->lock_version);
    \App\Models\StudentFinalProject::where('student_number', $s['student']->student_number)->update(['thesis_title_id' => 'Judul kemudian']);
    $info = app(\App\Services\SidangTaExecutionInfoService::class)->forRegistration($s['registration'], true);
    expect($info['title'])->toBe('Judul saat sidang')->and($info['result_status'])->toBe('passed');
});

test('legacy correction endpoint cannot move a schedule with a final result until the result is undone', function () {
    $s = $this->s; $staff = app(SidangTaStaffScheduling::class);
    $token = $staff->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0]['token'];
    $c = $staff->confirm($s['reviewer'], $s['registration'], $token);
    $c = app(\App\Services\SidangTaExecutionOutcomeService::class)->set($s['reviewer'], $s['registration'], 'passed', $c->lock_version);
    expect(fn () => app(\App\Services\SidangTaBookingAdministration::class)->reschedule($s['reviewer'], $s['registration'],
        ['date' => '2026-11-24', 'room_id' => $s['rooms']['b']->id, 'start_time' => '08:00', 'end_time' => '09:30'],
        $c->lock_version, 'Koreksi jadwal'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($c->booking->fresh()->start_date->format('Y-m-d'))->toBe('2026-11-23');
});

test('final result protects both general room cancellation and approval withdrawal', function (string $operation) {
    $s = $this->s; $staff = app(SidangTaStaffScheduling::class);
    $c = $staff->confirm($s['reviewer'], $s['registration'], $staff->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0]['token']);
    $c = app(\App\Services\SidangTaExecutionOutcomeService::class)->set($s['reviewer'], $s['registration'], 'passed', $c->lock_version);
    $action = $operation === 'room'
        ? fn () => app(\App\Services\RoomBookingTransitionService::class)->cancel($s['reviewer'], $c->booking, 'Koreksi')
        : fn () => app(\App\Services\SidangTaRegistrationReview::class)->cancelApproval($s['reviewer'], $s['registration'], $s['registration']->fresh()->lock_version);
    expect($action)->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($c->booking->fresh()->status)->toBe('approved')->and($s['registration']->fresh()->status)->toBe('approved');
})->with(['room', 'approval']);

test('completed execution admin detail preserves the actual room time and frozen identity', function () {
    $s = $this->s;
    \App\Models\StudentFinalProject::create(['student_number' => $s['student']->student_number, 'thesis_title_id' => 'Judul tetap 124']);
    $staff = app(SidangTaStaffScheduling::class);
    $c = $staff->confirm($s['reviewer'], $s['registration'], $staff->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0]['token']);
    $c->update(['state' => 'completed', 'active_student_number' => null]);
    \App\Models\StudentFinalProject::where('student_number', $s['student']->student_number)->update(['thesis_title_id' => 'Judul berubah 124']);
    $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'Asia/Jakarta'));
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-execution.show', $s['registration']))->assertOk()
        ->assertSee('Judul tetap 124')->assertDontSee('Judul berubah 124')->assertSee('08:00')->assertSee('09:30')->assertDontSee('Belum ditetapkan');
});

test('cancelled execution is labelled cancelled and export does not report a currently assigned room', function () {
    $s = $this->s; $s['rooms']['a']->update(['room_code' => 'R-CANCELLED-998']);
    $staff = app(SidangTaStaffScheduling::class);
    $option = collect($staff->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'])
        ->first(fn ($o) => $o['candidate']['room_id'] === $s['rooms']['a']->id);
    $c = $staff->confirm($s['reviewer'], $s['registration'], $option['token']);
    app(\App\Services\SidangTaBookingAdministration::class)->cancel($s['reviewer'], $s['registration'], $c->lock_version, 'Pembatalan');
    $this->actingAs($s['owner'])->get(route('sidang-ta-execution.show', $s['registration']))->assertOk()->assertSee('Dibatalkan');
    $response = app(\App\Services\SidangTaExecutionExport::class)->download(collect([$s['registration']]));
    $file = $response->getFile()->getPathname(); $zip = new ZipArchive; $zip->open($file);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml').($zip->getFromName('xl/sharedStrings.xml') ?: '');
    expect($xml)->not->toContain('R-CANCELLED-998'); $zip->close(); unlink($file);
});

test('admin assignment resolves old lecturer contexts without deleting their booking history', function (string $oldStatus) {
    $s = $this->s;
    $old = staffSchedulingBlock($s, ['room_id' => $s['rooms']['a']->id, 'date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
    $old->update(['status' => $oldStatus, 'sidang_ta_registration_id' => $s['registration']->id]);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number,
        'room_booking_request_id' => $old->id, 'mode' => 'lecturer',
        'state' => $oldStatus === 'pending' ? 'pending_lecturer' : $oldStatus,
        'active_student_number' => $oldStatus === 'pending' ? $s['student']->student_number : null, 'was_confirmed' => false]);
    $staff = app(SidangTaStaffScheduling::class);
    $option = collect($staff->preview($s['reviewer'], $s['registration'], '2026-11-23', '08:00', '09:30')['options'])
        ->first(fn ($o) => $o['candidate']['room_id'] === $s['rooms']['b']->id);
    $c = $staff->confirm($s['reviewer'], $s['registration'], $option['token']);
    expect($c->booking->status)->toBe('approved')->and($c->state)->toBe('confirmed')->and($c->was_confirmed)->toBeTrue();
    expect($old->fresh())->not->toBeNull();
    if ($oldStatus !== 'pending') expect($old->fresh()->status)->toBe($oldStatus)->and($c->booking->id)->not->toBe($old->id);
})->with(['pending', 'cancelled', 'rejected']);

test('TA reviewer cannot approve an ordinary pending lecturer booking without room review permission', function () {
    $s = $this->s;
    $old = staffSchedulingBlock($s, ['room_id' => $s['rooms']['a']->id, 'date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
    $old->update(['sidang_ta_registration_id' => $s['registration']->id]);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number,
        'room_booking_request_id' => $old->id, 'mode' => 'lecturer', 'state' => 'pending_lecturer',
        'active_student_number' => $s['student']->student_number, 'was_confirmed' => false]);
    $actor = User::factory()->create(['status' => 'active']);
    $actor->givePermissionTo('sidang_ta_registration_review');
    $staff = app(SidangTaStaffScheduling::class);
    $option = $staff->preview($actor, $s['registration'], '2026-11-23', '08:00', '09:30')['options'][0];
    expect(fn () => $staff->confirm($actor, $s['registration'], $option['token']))->toThrow(HttpException::class);
    expect($old->fresh()->status)->toBe('pending')->and(RoomBookingRequest::count())->toBe(1);
});
