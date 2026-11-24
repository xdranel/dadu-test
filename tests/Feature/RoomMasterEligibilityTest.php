<?php

use App\Models\Room;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->s['settings']->update(['duration_minutes' => 90]);
});

test('TA scheduling only offers usable rooms with a KELAS label', function () {
    $s = $this->s;
    $s['rooms']['lab']->update(['label' => 'LAB']);
    $s['rooms']['unknown']->update(['label' => null]);
    $inactive = Room::create(['room_code' => 'AAA-INACTIVE', 'name' => 'KELAS NONAKTIF',
        'building' => 'Dummy', 'capacity' => 20, 'status' => 'Tidak Dapat Dipakai', 'label' => 'KELAS']);
    $before = Room::orderBy('id')->get()->mapWithKeys(fn ($r) => [$r->id => $r->status])->all();
    $result = app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23');
    $ids = collect($result['options'])->pluck('candidate.room_id')->unique()->sort()->values()->all();
    expect($ids)->toBe([$s['rooms']['a']->id, $s['rooms']['b']->id])
        ->not->toContain($inactive->id);
    expect(Room::orderBy('id')->get()->mapWithKeys(fn ($r) => [$r->id => $r->status])->all())->toBe($before);
    expect(Schema::hasColumn('rooms', 'is_classroom'))->toBeFalse();
});

test('XLSX master import keeps coded rooms with incomplete details and preserves manual entries on reimport', function () {
    $actor = $this->s['reviewer'];
    $actor->givePermissionTo(Permission::findOrCreate('room_create', 'web'), Permission::findOrCreate('room_read', 'web'));
    $oldLab = Room::create(['room_code' => 'MANSYS LAB.', 'name' => '', 'building' => '', 'capacity' => 0]);
    $rows = [
        ['Kode Ruang' => 'KTT 1.27 B', 'Nama Ruang' => 'KELAS 1.27 B', 'Lokasi Gedung' => 'Gedung Utama', 'Kapasitas' => 80, 'Status' => 'Aktif', 'Label' => 'KELAS'],
        ['Kode Ruang' => 'MANSYS LAB', 'Nama Ruang' => '', 'Lokasi Gedung' => '', 'Kapasitas' => '', 'Status' => 'Aktif', 'Label' => 'lab'],
        ['Kode Ruang' => 'KTT A-1.03', 'Nama Ruang' => 'KELAS A-1.03', 'Lokasi Gedung' => 'Gedung AB', 'Kapasitas' => 45, 'Status' => 'Non Aktif', 'Label' => ''],
        ['Kode Ruang' => 'KTT AULA', 'Nama Ruang' => '', 'Lokasi Gedung' => 'TUS-GEDUNG SBS', 'Kapasitas' => 1, 'Status' => 'Non Aktif', 'Label' => 'AULA'],
    ];
    Storage::fake('local');
    $path = Storage::disk('local')->path('rooms.xlsx');
    (new FastExcel(collect($rows)))->export($path);
    $file = new UploadedFile($path, 'rooms.xlsx', null, UPLOAD_ERR_OK, true);

    $this->actingAs($actor)->post(route('rooms.import'), ['file' => $file])->assertRedirect(route('rooms.index'))
        ->assertSessionHas('success');
    $lab = Room::where('room_code', 'MANSYS LAB')->sole();
    $unlabelled = Room::where('room_code', 'KTT A-1.03')->sole();
    $hall = Room::where('room_code', 'KTT AULA')->sole();
    expect($lab->id)->toBe($oldLab->id)->and($lab->label)->toBe('LAB')->and($lab->status)->toBe('Dapat Dipakai')
        ->and($unlabelled->label)->toBeNull()->and($unlabelled->status)->toBe('Tidak Dapat Dipakai')
        ->and(Room::where('room_code', 'KTT 1.27 B')->sole()->label)->toBe('KELAS');
    expect($hall->label)->toBe('AULA')->and($hall->status)->toBe('Tidak Dapat Dipakai')
        ->and($hall->isBookableClassroom())->toBeFalse();
    $lab->update(['name' => 'Nama Lab Terverifikasi', 'building' => 'Gedung Lab', 'capacity' => 32]);
    $unlabelled->update(['label' => 'KELAS']);

    $this->post(route('rooms.import'), ['file' => $file])->assertRedirect(route('rooms.index'));
    expect($lab->fresh()->only(['name', 'building', 'capacity']))->toBe(['name' => 'Nama Lab Terverifikasi', 'building' => 'Gedung Lab', 'capacity' => 32])
        ->and($unlabelled->fresh()->label)->toBe('KELAS');
    $this->get(route('rooms.index', ['search' => 'MANSYS LAB']))->assertOk()
        ->assertSee('MANSYS LAB')->assertSee('Gedung Lab')->assertSee('32 orang')
        ->assertSee('Label')->assertSee('LAB')
        ->assertDontSee('<th>Nama Ruangan</th>', false)->assertDontSee('Nama Lab Terverifikasi');
});

test('room CRUD no longer requires classification and accepts an old open form without changing status', function () {
    $actor = $this->s['reviewer'];
    foreach (['room_read', 'room_create'] as $name) {
        $actor->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    $this->actingAs($actor)->get(route('rooms.index'))->assertOk()->assertDontSee('Klasifikasi TA');
    $this->get(route('rooms.create'))->assertOk()->assertDontSee('name="is_classroom"', false);
    $this->get(route('rooms.edit', $this->s['rooms']['a']))->assertOk()->assertDontSee('name="is_classroom"', false);
    $this->post(route('rooms.store'), ['room_code' => 'STATUS-OFF', 'name' => 'KELAS TETAP NONAKTIF',
        'building' => 'Dummy', 'capacity' => 20, 'status' => 'Tidak Dapat Dipakai', 'is_classroom' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect(route('rooms.index'));
    expect(Room::where('room_code', 'STATUS-OFF')->sole()->only(['name', 'status']))
        ->toBe(['name' => 'KELAS TETAP NONAKTIF', 'status' => 'Tidak Dapat Dipakai']);
});

test('room forms create and update rooms without requiring a name', function () {
    $actor = $this->s['reviewer'];
    $actor->givePermissionTo(Permission::findOrCreate('room_create', 'web'), Permission::findOrCreate('room_update', 'web'));
    $room = $this->s['rooms']['a'];
    $existingName = $room->name;
    $data = ['room_code' => ' ktt-tanpa-nama ', 'building' => 'Gedung A',
        'capacity' => 35, 'label' => 'KELAS', 'status' => 'Dapat Dipakai'];

    $this->actingAs($actor)->get(route('rooms.create'))->assertOk()->assertDontSee('name="name"', false);
    $this->get(route('rooms.edit', $room))->assertOk()->assertDontSee('name="name"', false);
    $this->post(route('rooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('rooms.index'));
    expect(Room::where('room_code', 'KTT-TANPA-NAMA')->sole()->only(['name', 'label', 'status']))
        ->toBe(['name' => 'KTT-TANPA-NAMA', 'label' => 'KELAS', 'status' => 'Dapat Dipakai']);

    $updates = ['room_code' => ' ktt-edit ', 'label' => 'LAB', 'status' => 'Tidak Dapat Dipakai'] + $data;
    $this->put(route('rooms.update', $room), $updates)
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Data KTT-EDIT berhasil diperbarui.');
    $this->put(route('rooms.update', $room), $updates + ['name' => ' '])->assertSessionHasNoErrors();

    expect($room->fresh()->only(['room_code', 'name', 'label', 'status']))
        ->toBe(['room_code' => 'KTT-EDIT', 'name' => $existingName, 'label' => 'LAB', 'status' => 'Tidak Dapat Dipakai']);
});

test('editing a room returns to the originating filtered page after saving or cancelling', function () {
    $actor = $this->s['reviewer'];
    $actor->givePermissionTo(Permission::findOrCreate('room_read', 'web'), Permission::findOrCreate('room_update', 'web'));
    $attributes = ['building' => 'Gedung Uji', 'capacity' => 20, 'status' => 'Dapat Dipakai'];
    $room = Room::create($attributes + ['room_code' => 'URUT-0', 'name' => 'Kelas Uji 0']);
    $this->travel(1)->minutes();
    foreach (range(1, 15) as $number) {
        Room::create($attributes + ['room_code' => 'URUT-'.$number, 'name' => 'Kelas Uji '.$number]);
    }

    $filters = ['page' => '2', 'search' => 'Kelas Uji', 'gedung' => 'Gedung Uji', 'status' => 'Dapat Dipakai'];
    $listUrl = route('rooms.index', $filters);
    $editUrl = route('rooms.edit', ['room' => $room, 'list' => $filters]);
    $updateUrl = route('rooms.update', ['room' => $room, 'list' => $filters]);

    $this->actingAs($actor)->get($listUrl)->assertOk()->assertSee($editUrl);
    $this->get($editUrl)->assertOk()->assertSee($updateUrl)->assertSee($listUrl);
    $this->from($editUrl)->put($updateUrl, ['room_code' => 'URUT-0', 'capacity' => 0] + $attributes)
        ->assertSessionHasErrors('capacity')->assertRedirect($editUrl);
    $this->get($editUrl)->assertOk()->assertSee($updateUrl);
    $this->put($updateUrl, ['status' => 'Tidak Dapat Dipakai', 'room_code' => 'URUT-0'] + $attributes)
        ->assertSessionHasNoErrors()->assertRedirect($listUrl);
    expect($room->fresh()->status)->toBe('Tidak Dapat Dipakai');
});

test('room pages have a stable order when an import creates rooms with identical timestamps', function () {
    $actor = $this->s['reviewer'];
    $actor->givePermissionTo(Permission::findOrCreate('room_read', 'web'));
    $codes = collect(range(1, 50))->map(fn (int $number) => sprintf('PAG-%02d', $number));
    foreach ($codes as $code) {
        Room::create(['room_code' => $code, 'name' => $code, 'building' => 'Gedung Paginasi', 'capacity' => 20]);
    }
    $expectedPages = $codes->reverse()->values()->chunk(15);

    $this->actingAs($actor);
    foreach (range(1, 4) as $page) {
        $response = $this->get(route('rooms.index', ['gedung' => 'Gedung Paginasi', 'page' => $page]))->assertOk();
        expect($response->viewData('rooms')->pluck('room_code')->all())
            ->toBe($expectedPages[$page - 1]->values()->all());
    }
});
