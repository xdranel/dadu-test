<?php

use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\RoomWeeklyPattern;
use App\Models\SidangTaSchedule;
use App\Models\User;
use App\Services\SidangTaBookingAccess;
use App\Services\SidangTaBookingLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->lecturer = User::factory()->create(['auth_source' => 'sso', 'status' => 'active']);
    $role = Role::findOrCreate('Pelaksana', 'web');
    $role->syncPermissions([]);
    $this->lecturer->assignRole($role);
    foreach (['room_booking_create', 'room_booking_read', 'room_booking_update', 'room_booking_review'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $this->lecturer->givePermissionTo(['room_booking_create', 'room_booking_read', 'room_booking_update']);
    $s = $this->s;
    RoomWeeklyPattern::create(['academic_period_id' => $s['academic']->id, 'room_id' => $s['rooms']['a']->id, 'weekday' => 1, 'start_time' => '08:30:00', 'end_time' => '10:30:00', 'reference_date' => '2026-11-09', 'source_identity' => 'pattern']);
    DB::table('room_import_logs')->insert(['user_id' => $s['reviewer']->id, 'filename' => 'old.json', 'rows_imported' => 1, 'errors' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('room_schedules')->insert(['room_id' => $s['rooms']['a']->id, 'usage_date' => '2026-11-09', 'start_time' => '08:30:00', 'end_time' => '10:30:00', 'source' => 'igracias']);
    $this->requestBooking = function (string $status = 'pending', bool $ta = false) {
        $s = $this->s;
        $b = RoomBookingRequest::create(['user_id' => $this->lecturer->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:30:00', 'end_time' => '10:30:00', 'purpose' => 'Test ordinary', 'participants' => 1, 'status' => $status, 'booking_origin' => $ta ? 'sidang_ta_automatic' : 'ordinary', 'sidang_ta_registration_id' => $ta ? $s['registration']->id : null]);
        $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:30:00', 'end_time' => '10:30:00']);

        return $b;
    };
});
afterEach(function () {
    $this->travelBack();
});
it('uses weekly occupancy for lecturer availability and store while keeping other room types available', function () {
    $this->actingAs($this->lecturer)->postJson(route('room-bookings.availability'), ['start_at' => '2026-11-23T08:30', 'end_at' => '2026-11-23T10:30'])->assertOk()->assertJsonMissing(['id' => $this->s['rooms']['a']->id]);
    $this->post(route('room-bookings.store'), ['room_id' => $this->s['rooms']['a']->id, 'start_at' => '2026-11-23T08:30', 'end_at' => '2026-11-23T10:30', 'purpose' => 'New'])->assertSessionHasErrors('availability');
    expect(RoomBookingRequest::count())->toBe(0);
    $this->postJson(route('room-bookings.availability'), ['start_at' => '2026-11-23T06:30', 'end_at' => '2026-11-23T07:30'])->assertOk()->assertJsonFragment(['id' => $this->s['rooms']['lab']->id]);
});
it('includes weekly classes in the daily calendar detail', function () {
    $this->actingAs($this->lecturer)->getJson(route('room-bookings.calendar.detail', ['date' => '2026-11-23']))->assertOk()->assertJsonCount(1, 'schedules')->assertJsonFragment(['start_time' => '08:30']);
});
it('does not approve a pending request that conflicts with the weekly source', function () {
    $b = ($this->requestBooking)();
    $this->actingAs($this->s['reviewer'])->post(route('admin.room-bookings.decision', $b), ['decision' => 'approved'])->assertSessionHasErrors('availability');
    expect($b->fresh()->status)->toBe('pending');
});
it('preserves the lecturer owner pending cancellation and rejects approved owner cancellation', function () {
    $b = ($this->requestBooking)();
    $this->actingAs($this->lecturer)->post(route('room-bookings.cancel', $b))->assertRedirect();
    expect($b->fresh()->status)->toBe('cancelled');
    $approved = ($this->requestBooking)('approved');
    $this->post(route('room-bookings.cancel', $approved))->assertStatus(422);
    expect($approved->fresh()->status)->toBe('approved');
});
it('does not let a room-only reviewer cancel a TA booking through the old endpoint', function () {
    $b = ($this->requestBooking)('approved', true);
    $actor = User::factory()->create(['status' => 'active']);
    $actor->givePermissionTo('room_booking_review');
    $this->actingAs($actor)->post(route('admin.room-bookings.cancel', $b), ['reason' => 'Not my authority'])->assertForbidden();
    expect($b->fresh()->status)->toBe('approved');
});
it('marks calendar dates unknown when the source is not verified instead of available', function () {
    $this->s['source']->update(['is_complete' => false]);
    $this->actingAs($this->lecturer)->get(route('room-bookings.index', ['month' => '2026-11']))->assertOk()->assertViewHas('calendar', fn ($calendar) => $calendar['2026-11-23'] === 'unknown')->assertSee('Jadwal belum tersedia');
});
it('provides the ordered TA lock context and enforces student ownership and staff access', function () {
    $s = $this->s;
    $service = app(SidangTaBookingLock::class);
    $id = $service->run($s['registration']->id, [], fn ($reg, $context) => $context->sidang_ta_registration_id);
    expect($id)->toBe($s['registration']->id)->and(SidangTaSchedule::count())->toBe(1);
    app(SidangTaBookingAccess::class)->authorizeStudent($s['owner'], $s['registration'], true);
    expect(fn () => app(SidangTaBookingAccess::class)->authorizeStaff($this->lecturer))->toThrow(HttpException::class);
});
it('never returns bookable daily slots for an unverified source', function () {
    $this->s['source']->update(['is_complete' => false]);
    $this->actingAs($this->lecturer)->getJson(route('room-bookings.calendar.detail', ['date' => '2026-11-23']))->assertOk()->assertJsonPath('source_ready', false)->assertJsonCount(0, 'available_slots');
});
it('batches ordinary availability queries instead of querying every room and day', function () {
    for ($i = 0; $i < 10; $i++) {
        Room::create(['room_code' => 'BATCH'.$i, 'name' => 'Batch '.$i, 'building' => 'Test', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
    }
    $this->actingAs($this->lecturer);
    DB::enableQueryLog();
    $this->postJson(route('room-bookings.availability'), ['start_at' => '2026-11-23T08:30', 'end_at' => '2026-11-27T10:30'])->assertOk();
    $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'room_weekly_patterns'));
    DB::disableQueryLog();
    expect($queries->count())->toBeLessThanOrEqual(2);
});
it('review111_purpose_limit_matches_existing_mysql_storage', function () {
    $this->actingAs($this->lecturer)->postJson(route('room-bookings.store'), ['room_id' => $this->s['rooms']['b']->id, 'start_at' => '2026-11-24T08:00', 'end_at' => '2026-11-24T10:00', 'purpose' => str_repeat('x', 256)])->assertUnprocessable()->assertJsonValidationErrors('purpose');
    $this->postJson(route('room-bookings.store'), ['room_id' => $this->s['rooms']['b']->id, 'start_at' => '2026-11-24T08:00', 'end_at' => '2026-11-24T10:00', 'purpose' => str_repeat('x', 255)])->assertRedirect();
    expect(RoomBookingRequest::sole()->purpose)->toHaveLength(255);
});
