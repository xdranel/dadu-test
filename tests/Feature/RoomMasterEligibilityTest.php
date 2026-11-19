<?php

use App\Models\Room;
use App\Services\SidangTaStaffScheduling;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaRoomScenario;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));
    $this->s = TaRoomScenario::make();
    $this->s['settings']->update(['duration_minutes' => 90]);
});

test('all usable master rooms are eligible without classification and inactive status is preserved', function () {
    $s = $this->s;
    $inactive = Room::create(['room_code' => 'AAA-INACTIVE', 'name' => 'KELAS NONAKTIF',
        'building' => 'Dummy', 'capacity' => 20, 'status' => 'Tidak Dapat Dipakai']);
    $before = Room::orderBy('id')->get()->mapWithKeys(fn ($r) => [$r->id => $r->status])->all();
    $result = app(SidangTaStaffScheduling::class)->preview($s['reviewer'], $s['registration'], '2026-11-23');
    $ids = collect($result['options'])->pluck('candidate.room_id')->unique()->sort()->values()->all();
    expect($ids)->toBe(collect($s['rooms'])->pluck('id')->sort()->values()->all())
        ->not->toContain($inactive->id);
    expect(Room::orderBy('id')->get()->mapWithKeys(fn ($r) => [$r->id => $r->status])->all())->toBe($before);
    expect(Schema::hasColumn('rooms', 'is_classroom'))->toBeFalse();
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
    expect(Room::where('room_code', 'STATUS-OFF')->sole()->status)->toBe('Tidak Dapat Dipakai');
});
