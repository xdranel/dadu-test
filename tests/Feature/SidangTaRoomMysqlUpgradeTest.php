<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest;
use App\Models\User;
use Tests\Support\TaRoomCommittedTestCase;

class SidangTaRoomMysqlUpgradeTest extends TaRoomCommittedTestCase
{
    public function test_native_mysql_upgrade_preserves_existing_pending_and_approved_bookings(): void
    {
        $names = ['2026_11_15_110001_create_room_weekly_patterns.php', '2026_11_15_110002_create_sidang_ta_scheduling_tables.php', '2026_11_15_110003_extend_room_booking_for_sidang_ta.php'];
        foreach (array_reverse($names) as $name) {
            (require database_path('migrations/'.$name))->down();
        }
        $u = User::factory()->create();
        $r = Room::create(['room_code' => 'UPGRADE', 'name' => 'KELAS UJI', 'building' => 'Test', 'capacity' => 20, 'status' => 'Dapat Dipakai']);
        $before = [];
        foreach (['pending', 'approved'] as $status) {
            $b = RoomBookingRequest::create(['user_id' => $u->id, 'room_id' => $r->id, 'type' => 'single', 'participants' => 1, 'purpose' => 'Upgrade test', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => $status]);
            $o = $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
            $h = RoomBookingHistory::create(['request_id' => $b->id, 'actor_id' => $u->id, 'to_status' => $status, 'note' => 'Existing history']);
            $before[] = [$b->fresh()->getRawOriginal(), $o->fresh()->getRawOriginal(), $h->fresh()->getRawOriginal()];
        }
        foreach ($names as $name) {
            (require database_path('migrations/'.$name))->up();
        }
        foreach ($before as [$raw,$occ,$history]) {
            $b = RoomBookingRequest::findOrFail($raw['id']);
            $this->assertSame($raw, array_intersect_key($b->getRawOriginal(), $raw));
            $this->assertSame($occ, $b->occurrences()->sole()->getRawOriginal());
            $this->assertSame($history, array_intersect_key($b->histories()->sole()->getRawOriginal(), $history));
            $this->assertSame('ordinary', $b->booking_origin);
            $this->assertNull($b->sidang_ta_registration_id);
        }
    }
}
