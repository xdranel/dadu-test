<?php

namespace Tests\Feature;

use App\Models\RoomBookingRequest;
use App\Models\SidangTaSchedule;
use App\Models\Student;
use App\Models\User;
use App\Services\RoomBookingTransitionService as Transition;
use App\Services\RoomScheduleImportService as Importer;
use App\Services\SidangTaBookingAdministration as Admin;
use App\Services\SidangTaBookingService as Booking;
use App\Services\SidangTaExecutionWindowService as Settings;
use App\Services\SidangTaRegistrationReview as Review;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TaRoomCommittedTestCase;
use Tests\Support\TaRoomConcurrencyHarness;
use Tests\Support\TaRoomScenario;

class SidangTaBookingConcurrencyTest extends TaRoomCommittedTestCase
{
    protected function setUp(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl for real independent processes.');
        }
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    }

    private function scenario(): array
    {
        $s = TaRoomScenario::make();
        $s['rooms']['b']->update(['is_classroom' => false]);

        return $s;
    }

    private function assertInvariants(array $results): void
    {
        foreach ($results as $r) {
            $this->assertNotSame('internal', $r['kind'] ?? null, json_encode($results));
        }
        $slots = DB::table('room_booking_occurrences as o')->join('room_booking_requests as b', 'b.id', '=', 'o.request_id')->whereIn('b.status', ['pending', 'approved'])->get(['b.id', 'b.room_id', 'o.usage_date', 'o.start_time', 'o.end_time']);
        foreach ($slots as $i => $a) {
            foreach ($slots as $j => $b) {
                if ($j > $i && $a->room_id === $b->room_id && $a->usage_date === $b->usage_date) {
                    $this->assertFalse($a->start_time < $b->end_time && $a->end_time > $b->start_time, 'Overlapping committed bookings');
                }
            }
        }
        foreach (SidangTaSchedule::with('booking')->get() as $c) {
            if ($c->state === 'confirmed') {
                $this->assertSame('approved', $c->booking?->status);
            }
        }
    }

    public function test_two_students_cannot_confirm_the_same_preview_slot(): void
    {
        $s = $this->scenario();
        $u = User::factory()->create(['status' => 'active']);
        $u->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_create']);
        $st = Student::create(['student_number' => '1110002', 'name' => 'Dummy kedua', 'email' => $u->email, 'academic_year' => 2022, 'study_program' => 'Informatika']);
        $reg = $s['registration']->replicate();
        $reg->fill(['student_number' => $st->student_number, 'student_name' => $st->name, 'submitted_by' => $u->id]);
        $reg->save();
        $p = app(Booking::class)->preview($s['owner'], $s['registration'], '2026-11-23');
        $q = app(Booking::class)->preview($u, $reg, '2026-11-23');
        $results = TaRoomConcurrencyHarness::race([fn () => app(Booking::class)->confirm($s['owner'], $s['registration'], $p['preview_token'])->id, fn () => app(Booking::class)->confirm($u, $reg, $q['preview_token'])->id]);
        $this->assertInvariants($results);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame(1, RoomBookingRequest::count());
    }

    public function test_confirm_races_mark_lecturer(): void
    {
        $s = $this->scenario();
        $p = app(Booking::class)->preview($s['owner'], $s['registration'], '2026-11-23');
        $results = TaRoomConcurrencyHarness::race([fn () => app(Booking::class)->confirm($s['owner'], $s['registration'], $p['preview_token'])->id, fn () => app(Admin::class)->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen')->id]);
        $this->assertInvariants($results);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
    }

    public function test_import_races_confirmation(): void
    {
        $s = $this->scenario();
        $p = app(Booking::class)->preview($s['owner'], $s['registration'], '2026-11-23');
        $payload = ['source' => 'iGracias', 'total' => 1, 'selected_menus' => ['jadwal_perkuliahan'], 'filter_jadwal_perkuliahan' => ['schoolYear' => '2627/1', 'studyProgramId' => '', 'roomId' => '', 'isConflict' => ''], 'data' => [['periode' => '2627/1', 'hari' => 'SENIN', 'tanggal' => '09-11-2026', 'ruangan' => 'A', 'jam_mulai' => '08:30:00', 'jam_selesai' => '10:30:00', 'jenis_jadwal' => 'TETAP', 'kode_dosen' => 'DUM', 'nama_mk' => 'Dummy']]];
        $results = TaRoomConcurrencyHarness::race([fn () => app(Booking::class)->confirm($s['owner'], $s['registration'], $p['preview_token'])->id, fn () => app(Importer::class)->import($payload, $s['reviewer'], 'race.json')['successful']]);
        $this->assertInvariants($results);
        $this->assertFalse($results[0]['ok'] && ($results[1]['value'] ?? false));
    }

    public function test_setting_change_races_confirmation(): void
    {
        $s = $this->scenario();
        $p = app(Booking::class)->preview($s['owner'], $s['registration'], '2026-11-23');
        $v = $s['settings']->only(['academic_period_id', 'allowed_weekdays', 'duration_minutes']);
        $v += ['start_date' => '2026-11-23', 'end_date' => '2026-11-27', 'start_time' => '09:00', 'end_time' => '16:00', 'break_start_time' => '12:00', 'break_end_time' => '13:00'];
        $results = TaRoomConcurrencyHarness::race([fn () => app(Booking::class)->confirm($s['owner'], $s['registration'], $p['preview_token'])->id, fn () => app(Settings::class)->save($s['reviewer'], $s['period'], $v, 1)->id]);
        $this->assertInvariants($results);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
    }

    private function lecturerLinked(array $s, bool $link): array
    {
        $c = app(Admin::class)->markLecturer($s['reviewer'], $s['registration'], 0, 'Dosen');
        $b = RoomBookingRequest::create(['user_id' => $s['reviewer']->id, 'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'participants' => 1, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00', 'purpose' => 'Dosen', 'status' => 'pending']);
        $b->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '10:00']);
        if ($link) {
            $c = app(Admin::class)->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Tautkan');
        }

return [$c, $b->fresh()];
    }

    public function test_link_races_room_cancellation(): void
    {
        $s = $this->scenario();
        [$c,$b] = $this->lecturerLinked($s, false);
        $results = TaRoomConcurrencyHarness::race([fn () => app(Admin::class)->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Tautkan')->id, fn () => app(Transition::class)->cancel($s['reviewer'], $b, 'Batal dosen')->id]);
        $this->assertInvariants($results);
    }

    public function test_link_races_room_approval(): void
    {
        $s = $this->scenario();
        [$c,$b] = $this->lecturerLinked($s, false);
        $results = TaRoomConcurrencyHarness::race([fn () => app(Admin::class)->linkLecturerBooking($s['reviewer'], $s['registration'], $b, $c->lock_version, 'Tautkan')->id, fn () => app(Transition::class)->decide($s['reviewer'], $b, 'approved', 'Setuju')->id]);
        $this->assertInvariants($results);
    }

    public function test_ta_withdrawal_races_linked_room_approval(): void
    {
        $s = $this->scenario();
        [$c,$b] = $this->lecturerLinked($s, true);
        $version = $s['registration']->fresh()->lock_version;
        $results = TaRoomConcurrencyHarness::race([fn () => app(Review::class)->cancelApproval($s['reviewer'], $s['registration'], $version)->id, fn () => app(Transition::class)->decide($s['reviewer'],$b,'approved','Setuju')->id]);
        $this->assertInvariants($results);
        $this->assertSame('submitted',$s['registration']->fresh()->status);
        $this->assertSame('cancelled',$b->fresh()->status);
    }
}
