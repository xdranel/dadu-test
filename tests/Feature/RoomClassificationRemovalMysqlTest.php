<?php

namespace Tests\Feature;

use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TaRoomCommittedTestCase;

class RoomClassificationRemovalMysqlTest extends TaRoomCommittedTestCase
{
    public function test_removing_obsolete_classification_preserves_every_room_status_booking_alias_and_history(): void
    {
        $path = database_path('migrations/2026_11_19_128000_remove_room_ta_classification.php');
        $this->assertFileExists($path);
        $migration = require $path;
        $migration->down();
        $actor = User::factory()->create();
        foreach (['Dapat Dipakai', 'Tidak Dapat Dipakai'] as $status) {
            foreach ([true, false, null] as $flag) {
                $id = DB::table('rooms')->insertGetId(['room_code' => 'KEEP-'.DB::table('rooms')->count(),
                    'name' => 'KELAS UJI', 'building' => 'Uji', 'capacity' => 30, 'status' => $status,
                    'is_classroom' => $flag, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('room_schedule_aliases')->insert(['room_id' => $id, 'alias_key' => 'KEEPALIAS'.$id,
                    'label' => 'Alias '.$id, 'created_at' => now(), 'updated_at' => now()]);
                $booking = RoomBookingRequest::create(['user_id' => $actor->id, 'room_id' => $id,
                    'type' => 'single', 'purpose' => 'Histori dipertahankan', 'participants' => 1,
                    'status' => $flag === false ? 'pending' : 'approved', 'start_date' => '2026-11-23',
                    'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
                $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
                RoomBookingHistory::create(['request_id' => $booking->id, 'actor_id' => $actor->id,
                    'to_status' => $booking->status, 'note' => 'Histori semula']);
            }
        }
        $rooms = DB::table('rooms')->orderBy('id')->get()->map(fn ($r) => array_diff_key((array) $r, ['is_classroom' => true]))->all();
        $tables = ['room_booking_requests', 'room_booking_occurrences', 'room_booking_histories', 'room_schedule_aliases'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('rooms', 'is_classroom'));
        $this->assertEquals($rooms, DB::table('rooms')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        foreach ($tables as $table) $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get()->toArray());
        $migration->down();
        $this->assertTrue(Schema::hasColumn('rooms', 'is_classroom'));
        $this->assertSame(6, DB::table('rooms')->where('is_classroom', true)->count());
        $this->assertEquals($rooms, DB::table('rooms')->orderBy('id')->get()->map(fn ($r) => array_diff_key((array) $r, ['is_classroom' => true]))->all());
        $migration->up();
        foreach ($tables as $table) $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get()->toArray());
    }
}
