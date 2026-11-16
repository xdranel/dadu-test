<?php

use App\Models\AcademicPeriod;
use App\Models\RoomBookingRequest;
use App\Models\RoomWeeklyPattern;
use App\Services\RoomAvailabilityService;
use App\Services\RoomScheduleImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaRoomScenario;

function imported111Payload(string $room = 'A'): array
{
    return ['source' => 'iGracias', 'total' => 1, 'selected_menus' => ['jadwal_perkuliahan'], 'filter_jadwal_perkuliahan' => ['schoolYear' => '2627/1', 'studyProgramId' => '', 'roomId' => '', 'isConflict' => ''], 'data' => [['periode' => '2627/1', 'hari' => 'SENIN', 'tanggal' => '09-11-2026', 'ruangan' => $room, 'jam_mulai' => '08:30:00', 'jam_selesai' => '10:30:00', 'jenis_jadwal' => 'TETAP', 'kode_dosen' => 'DUM', 'nama_mk' => 'Dummy']]];
}
beforeEach(function () {
    $this->s = TaRoomScenario::make();
});
it('imports semester patterns without deleting legacy data changing master status or other semesters', function () {
    $s = $this->s;
    $other = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 1, 'label' => 'Old', 'start_date' => '2025-09-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    RoomWeeklyPattern::create(['academic_period_id' => $other->id, 'room_id' => $s['rooms']['a']->id, 'weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'reference_date' => '2025-11-10', 'source_identity' => 'old']);
    DB::table('room_schedules')->insert(['room_id' => $s['rooms']['a']->id, 'usage_date' => '2026-11-09', 'start_time' => '08:30:00', 'end_time' => '10:30:00', 'source' => 'igracias']);
    $s['rooms']['b']->update(['status' => 'Tidak Dapat Dipakai']);
    $r = app(RoomScheduleImportService::class)->import(imported111Payload(), $s['reviewer'], 'test.json');
    expect($r['successful'])->toBeTrue()->and(RoomWeeklyPattern::count())->toBe(2)->and(DB::table('room_schedules')->count())->toBe(1)->and($s['rooms']['b']->fresh()->status)->toBe('Tidak Dapat Dipakai');
    expect(app(RoomAvailabilityService::class)->busyIntervals($s['rooms']['a']->id, '2026-11-23'))->toBe([['start' => 510, 'end' => 630]]);
});
it('rejects a replacement that conflicts with approved or pending bookings atomically', function (string $status) {
    $s = $this->s;
    $before = $s['source']->fresh()->getRawOriginal();
    $b = RoomBookingRequest::create(['user_id' => $s['owner']->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'purpose' => 'Retain me', 'participants' => 1, 'status' => $status]);
    $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
    $r = app(RoomScheduleImportService::class)->import(imported111Payload(), $s['reviewer'], 'conflict.json');
    expect($r['successful'])->toBeFalse()->and($r['errors'][0])->toContain('23')->and(RoomWeeklyPattern::count())->toBe(0)->and($s['source']->fresh()->getRawOriginal())->toBe($before)->and($b->fresh()->status)->toBe($status);
})->with(['pending', 'approved']);
it('requires an explicit room alias instead of inventing a second physical room', function () {
    $s = $this->s;
    $service = app(RoomScheduleImportService::class);
    $failed = $service->import(imported111Payload('KTT A'), $s['reviewer'], 'unknown.json');
    expect($failed['successful'])->toBeFalse()->and(RoomWeeklyPattern::count())->toBe(0)->and(DB::table('rooms')->count())->toBe(4);
    DB::table('room_schedule_aliases')->insert(['room_id' => $s['rooms']['a']->id, 'alias_key' => 'KTTA', 'label' => 'KTT A']);
    $r = $service->import(imported111Payload('KTT A'), $s['reviewer'], 'mapped.json');
    expect($r['successful'])->toBeTrue()->and(RoomWeeklyPattern::firstOrFail()->room_id)->toBe($s['rooms']['a']->id);
});
it('rejects partial snapshots without changing the complete source', function () {
    $s = $this->s;
    $p = imported111Payload();
    $p['filter_jadwal_perkuliahan']['studyProgramId'] = 'some-program';
    expect(app(RoomScheduleImportService::class)->import($p, $s['reviewer'], 'partial.json')['successful'])->toBeFalse();
    expect($s['source']->fresh()->fingerprint)->toBe('dummy-initial');
});
it('accepts weekly room JSON through the existing authorized sync upload endpoint', function () {
    $s = $this->s;
    foreach (['sync_execute', 'sync_read'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $s['reviewer']->givePermissionTo(['sync_execute', 'sync_read']);
    $file = UploadedFile::fake()->createWithContent('schedule.json', json_encode(imported111Payload()));
    $this->actingAs($s['reviewer'])->post(route('sync.import-json'), ['type' => 'room_schedule', 'json_file' => $file])->assertSessionHasNoErrors()->assertRedirect(route('sync.index'));
    expect(RoomWeeklyPattern::count())->toBe(1);
    $this->get(route('sync.index'))->assertOk()->assertSee('room_schedule', false)->assertSee('pola mingguan');
});
it('rejects a legacy dated replacement that overlaps a live internal booking', function () {
    $s = $this->s;
    $b = RoomBookingRequest::create(['user_id' => $s['owner']->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'participants' => 1, 'purpose' => 'Keep', 'status' => 'approved', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']);
    $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']);
    $before = DB::table('room_schedules')->get()->toArray();
    $result = app(RoomScheduleImportService::class)->import(['source' => 'iGracias', 'data' => [['room_code' => 'A', 'date' => '23-11-2026', 'start_time' => '08:30', 'end_time' => '10:30']]], $s['reviewer'], 'legacy.json');
    expect($result['successful'])->toBeFalse()->and(DB::table('room_schedules')->get()->toArray())->toEqual($before)->and($b->fresh()->status)->toBe('approved');
});
