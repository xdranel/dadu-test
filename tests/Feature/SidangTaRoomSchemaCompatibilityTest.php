<?php

use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('keeps TA scheduling schema without an extra room classification requirement', function () {
    foreach (['room_weekly_patterns', 'room_schedule_sources', 'sidang_ta_execution_settings', 'sidang_ta_schedules'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
    $room = Room::create(['room_code' => 'SCHEMA-A', 'name' => 'KELAS A', 'building' => 'Test', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
    expect(Schema::hasColumn('rooms', 'is_classroom'))->toBeFalse();
    expect(Schema::hasColumns('room_booking_requests', ['booking_origin', 'sidang_ta_registration_id']))->toBeTrue();
});

it('upgrades existing room bookings and histories without changing their original fields', function (string $status) {
    $classification = require database_path('migrations/2026_11_19_128000_remove_room_ta_classification.php');
    $classification->down();
    $execution = require database_path('migrations/2026_11_19_124000_extend_sidang_ta_execution_admin.php');
    $execution->down();
    $names = ['2026_11_15_110001_create_room_weekly_patterns.php', '2026_11_15_110002_create_sidang_ta_scheduling_tables.php', '2026_11_15_110003_extend_room_booking_for_sidang_ta.php'];
    foreach (array_reverse($names) as $name) {
        (require database_path('migrations/'.$name))->down();
    }
    $user = User::factory()->create();
    $room = Room::create(['room_code' => 'OLD-A', 'name' => 'KELAS LAMA', 'building' => 'Test', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
    $booking = RoomBookingRequest::create(['user_id' => $user->id, 'room_id' => $room->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'purpose' => 'Existing request', 'participants' => 2, 'status' => $status]);
    $occurrence = $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
    $history = RoomBookingHistory::create(['request_id' => $booking->id, 'actor_id' => $user->id, 'to_status' => $status, 'note' => 'Existing history']);
    $before = $booking->fresh()->getRawOriginal();
    $occurrenceBefore = $occurrence->fresh()->getRawOriginal();
    $historyBefore = $history->fresh()->getRawOriginal();
    foreach ($names as $name) {
        (require database_path('migrations/'.$name))->up();
    }
    $execution->up();
    $classification->up();
    expect(array_intersect_key($booking->fresh()->getRawOriginal(), $before))->toBe($before)
        ->and($booking->fresh()->booking_origin)->toBe('ordinary')
        ->and($booking->fresh()->sidang_ta_registration_id)->toBeNull()
        ->and($occurrence->fresh()->getRawOriginal())->toBe($occurrenceBefore)
        ->and(array_intersect_key($history->fresh()->getRawOriginal(), $historyBefore))->toBe($historyBefore);
})->with(['pending', 'approved'])->skip(fn () => DB::getDriverName() === 'mysql', 'Native MySQL upgrade is verified by SidangTaRoomMysqlUpgradeTest without transactional DDL.');

it('keeps the nullable booking foreign key enforced without rebuilding legacy children', function () {
    $user = User::factory()->create();
    $room = Room::create(['room_code' => 'FK-A', 'name' => 'KELAS FK', 'building' => 'Test', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
    expect(fn () => RoomBookingRequest::create(['user_id' => $user->id, 'room_id' => $room->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'purpose' => 'Foreign key test', 'participants' => 2, 'status' => 'pending', 'sidang_ta_registration_id' => 999999]))->toThrow(QueryException::class);
});
