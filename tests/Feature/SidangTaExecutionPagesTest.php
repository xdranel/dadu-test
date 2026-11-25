<?php

use App\Models\Lecturer;
use App\Models\RoomBookingRequest;
use App\Models\User;
use App\Services\SidangTaExecutionInfoService;
use App\Services\SidangTaPlottingImportService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
});

test('execution participant page and settings are separate pages', function () {
    $response = $this->actingAs($this->s['reviewer'])->get('/dashboard/admin/sidang-ta-execution')
        ->assertOk()->assertDontSee('Ringkasan hasil filter')->assertSee($this->s['student']->student_number)
        ->assertSee('Impor plotting')->assertSee('Unduh Template')->assertSee('Export Excel')
        ->assertDontSee('Pilih periode dan file Excel plotting peserta.');
    $page = new Crawler($response->getContent());
    expect($page->filter('[data-execution-filters] [name]')->each(fn ($field) => $field->attr('name')))
        ->toBe(['search', 'period', 'status', 'study_program'])
        ->and($page->filter('.campus-card > div > [data-execution-count]')->count())->toBe(6)
        ->and($page->filter('#plotting-file')->attr('type'))->toBe('file');
    $this->get('/dashboard/admin/sidang-ta-execution/settings')->assertOk()
        ->assertSee('Semester sumber jadwal')->assertSee('90')->assertSee('Kembali');
});

test('student execution pages have identity cards and no scheduling controls', function () {
    $s = $this->s;
    $this->actingAs($s['owner'])->get('/dashboard/sidang-ta-execution')->assertOk()
        ->assertDontSee('Peserta TA')->assertSee('Plotting Jadwal')->assertSee('Riwayat Pelaksanaan')->assertSee('Aksi')
        ->assertSee(route('sidang-ta-execution.show', $s['registration']))
        ->assertDontSee('Tetapkan Jadwal')->assertDontSee('Cari ketersediaan');
    $this->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertOk()
        ->assertSee('Peserta TA')->assertSee($s['student']->student_number)->assertSee('Riwayat Status')->assertDontSee('ID Pengajuan');
    $other = User::factory()->create(['status' => 'active']);
    $other->givePermissionTo('sidang_ta_registration_read');
    $this->actingAs($other)->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertForbidden();
});

test('admin and student execution detail render the same timeline without exposing staff notes to students', function () {
    $s = $this->s;
    $schedule = $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number]);
    $schedule->executionLogs()->create([
        'action' => 'scheduled', 'notes' => 'Catatan internal penetapan',
        'performed_by' => $s['reviewer']->id, 'performed_at' => now()->subMinute(),
    ]);
    $schedule->executionLogs()->create([
        'action' => 'result_updated', 'from_status' => 'pending', 'to_status' => 'passed',
        'notes' => 'Catatan internal hasil', 'performed_by' => $s['reviewer']->id, 'performed_at' => now(),
    ]);

    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSeeInOrder(['Riwayat Status', 'Perubahan hasil · Belum ada hasil → Lulus', 'Penetapan jadwal'])
        ->assertSee('Catatan internal hasil');
    $this->actingAs($s['owner'])->get(route('sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSeeInOrder(['Riwayat Status', 'Perubahan hasil · Lulus', 'Penetapan jadwal'])
        ->assertDontSee('Catatan internal hasil')->assertDontSee('Catatan internal penetapan');
});

test('period reader cannot inspect participants and participant identifiers use UUIDs', function () {
    $reader = User::factory()->create(['status' => 'active']);
    $reader->givePermissionTo('sidang_ta_period_read');
    $this->actingAs($reader)->get('/dashboard/admin/sidang-ta-execution')->assertForbidden();
    $this->get('/dashboard/admin/sidang-ta-execution/settings')->assertOk();
    $this->actingAs($this->s['reviewer'])->get('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->id)
        ->assertClientError()->assertDontSee($this->s['student']->student_number);
});

test('program reader cannot expand execution filters or write results or export', function () {
    Permission::findOrCreate('sidang_ta_registration_view_program', 'web');
    $reader = User::factory()->create(['status' => 'active']);
    $reader->givePermissionTo('sidang_ta_registration_view_program');
    Lecturer::create(['lecturer_code' => 'EXEC', 'name' => 'Pembaca Pelaksanaan', 'email_domain' => $reader->email, 'study_program' => 'Informatika']);
    $foreign = $this->s['registration']->replicate();
    $foreign->student_number = '2220001';
    $foreign->student_name = 'Peserta Prodi Lain';
    $foreign->study_program = 'Sistem Informasi';
    $foreign->save();
    $this->actingAs($reader)->get('/dashboard/admin/sidang-ta-execution?study_program=Sistem%20Informasi')->assertOk()
        ->assertSee($this->s['student']->student_number)->assertDontSee('Peserta Prodi Lain')->assertDontSee('Export Excel')
        ->assertDontSee('name="study_program"', false)->assertDontSee('Akses baca Program Studi');
    $this->get('/dashboard/admin/sidang-ta-execution/'.$foreign->getRouteKey())->assertForbidden();
    $this->get('/dashboard/admin/sidang-ta-execution/export')->assertForbidden();
    $this->get('/dashboard/admin/sidang-ta-execution/template')->assertForbidden();
    $this->post('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->getRouteKey().'/outcome', ['status' => 'passed', 'context_version' => 0])->assertForbidden();
});

test('plotting template has two valid headers and one example row that the uploader accepts', function () {
    $s = $this->s;
    $response = $this->actingAs($s['reviewer'])->get('/dashboard/admin/sidang-ta-execution/template')
        ->assertOk()->assertDownload('Template_Plotting_Sidang_TA.xlsx');
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $reader = new Reader;
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = array_map(fn ($row) => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
                break;
            }
        } finally {
            $reader->close();
        }

        expect($rows)->toHaveCount(3)
            ->and($rows[0][0])->toBe('Peserta TA')->and($rows[0][9])->toBe('Plotting Jadwal')
            ->and($rows[1])->toBe(SidangTaPlottingImportService::HEADERS)
            ->and($rows[2])->toHaveCount(16)->and($rows[2][1])->toBe('0000000000');

        $s['period']->update(['name' => $rows[2][0]]);
        $s['registration']->update(['student_number' => $rows[2][1]]);
        $file = new UploadedFile($path, 'Template_Plotting_Sidang_TA.xlsx', null, null, true);
        $this->post(route('admin.sidang-ta-execution.import'), ['period' => $s['period']->getRouteKey(), 'file' => $file])
            ->assertSessionHasNoErrors()->assertSessionHas('import_result', fn ($result) => count($result['processed']) === 1 && $result['errors'] === []);
        expect(RoomBookingRequest::count())->toBe(0);
    } finally {
        unlink($path);
    }
});

test('manual plotting import requires a file while system scheduling does not', function () {
    $this->actingAs($this->s['reviewer'])->post('/dashboard/admin/sidang-ta-execution/import', ['period' => $this->s['period']->getRouteKey()])
        ->assertSessionHasErrors('file');
    $this->get('/dashboard/admin/sidang-ta-execution/'.$this->s['registration']->getRouteKey())->assertOk()
        ->assertSee('Cari ketersediaan')->assertDontSee('Impor terlebih dahulu');
});

test('staff date picker is bounded by execution dates and excludes past days', function () {
    $s = $this->s;
    $this->actingAs($s['reviewer'])->get(route('admin.sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSee('min="2026-11-23"', false)->assertSee('max="2026-11-27"', false);
    $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'Asia/Jakarta'));
    $this->get(route('admin.sidang-ta-execution.show', $s['registration']))
        ->assertOk()->assertSee('min="2026-11-24"', false)->assertSee('max="2026-11-27"', false);
    $this->post(route('admin.sidang-ta-execution.preview', $s['registration']), ['date' => '2026-11-28'])
        ->assertSessionHasErrors('date');
    expect(RoomBookingRequest::count())->toBe(0);
});

test('staff search presents classroom code location and capacity instead of the room name', function () {
    $s = $this->s;
    $s['rooms']['a']->update(['room_code' => 'KTT-UI', 'name' => 'Nama Lama Ruang Uji', 'building' => 'Gedung Uji', 'capacity' => 42]);
    $response = $this->actingAs($s['reviewer'])->post(route('admin.sidang-ta-execution.preview', $s['registration']), ['date' => '2026-11-23'])
        ->assertOk()->assertDontSee('Nama Lama Ruang Uji');
    $options = (new Crawler($response->getContent()))->filter('select[name="preview_token"]')->text();
    expect($options)->toContain('KTT-UI', 'Gedung Uji', 'kapasitas 42');
});

test('active info ends after actual occurrence while historical detail keeps its snapshot', function () {
    $s = $this->s;
    $booking = RoomBookingRequest::create(['user_id' => $s['reviewer']->id,
        'room_id' => $s['rooms']['a']->id, 'type' => 'single', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23',
        'start_time' => '08:00', 'end_time' => '09:30', 'purpose' => 'Sidang', 'participants' => 1,
        'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $s['registration']->id]);
    $booking->occurrences()->create(['usage_date' => '2026-11-23', 'start_time' => '08:00', 'end_time' => '09:30']);
    $s['registration']->roomSchedule()->create(['student_number' => $s['student']->student_number, 'state' => 'confirmed',
        'was_confirmed' => true, 'room_booking_request_id' => $booking->id, 'participant_data' => ['title' => 'Judul saat ditetapkan']]);
    app(SidangTaExecutionInfoService::class)->capture($s['registration']);
    $s['registration']->roomSchedule()->update(['participant_data' => json_encode(['title' => 'Judul terbaru'])]);
    $this->travelTo(Carbon::parse('2026-11-23 09:30:01', 'Asia/Jakarta'));
    $this->actingAs($s['owner'])->get('/dashboard/sidang-ta-execution')->assertOk()->assertSee('Belum ada pelaksanaan sidang aktif.');
    $this->get('/dashboard/sidang-ta-execution/'.$s['registration']->getRouteKey())->assertOk()
        ->assertSee('Judul saat ditetapkan')->assertDontSee('Judul terbaru')->assertSee('08:00')->assertSee('09:30');
});
