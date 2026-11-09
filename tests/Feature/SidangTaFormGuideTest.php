<?php

use App\Models\SidangTaFormGuide;
use App\Models\User;
use App\Services\SidangTaFormGuideService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    foreach (['Akademik', 'Petugas', 'mahasiswa'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    foreach (['sidang_ta_registration_read', 'sidang_ta_registration_review'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->academic = User::factory()->create(['status' => 'active']);
    $this->academic->assignRole('Akademik');
    $this->payload = ['slot' => 'ta_data_capture', 'title' => 'Panduan TA', 'description' => '<script>alert(1)</script>',
        'url' => 'https://example.test/guide', 'link_label' => 'Buka panduan', 'sort_order' => 2, 'is_active' => true];
});

test('guide administration rejects a role without the required permissions', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('Petugas');
    $guide = SidangTaFormGuide::create($this->payload);
    $this->actingAs($user);
    $this->get(route('admin.sidang-ta-guides.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-guides.create'))->assertForbidden();
    $this->post(route('admin.sidang-ta-guides.store'), $this->payload)->assertForbidden();
    $this->get(route('admin.sidang-ta-guides.edit', $guide))->assertForbidden();
    $this->put(route('admin.sidang-ta-guides.update', $guide), $this->payload)->assertForbidden();
    $this->delete(route('admin.sidang-ta-guides.destroy', $guide))->assertForbidden();
    $this->get(route('dashboard'))->assertDontSee(route('admin.sidang-ta-guides.index'), false);
});

test('existing Akademik reaches CRUD and sidebar after initializer without account seeders', function () {
    $count = User::count();
    $this->artisan('sidang-ta:initialize-form-guides')->assertSuccessful();
    expect(User::count())->toBe($count);
    expect(Role::findByName('Akademik')->hasPermissionTo('sidang_ta_form_guide_read'))->toBeTrue();
    $this->actingAs($this->academic)->get(route('dashboard'))->assertSee(route('admin.sidang-ta-guides.index'), false);
    $this->get(route('admin.sidang-ta-guides.index'))->assertOk();
    $this->get(route('admin.sidang-ta-guides.create'))->assertOk()->assertSee('diploma_biodata');
    $this->post(route('admin.sidang-ta-guides.store'), $this->payload)->assertRedirect(route('admin.sidang-ta-guides.index'));
    $guide = SidangTaFormGuide::where('title', 'Panduan TA')->firstOrFail();
    $this->get(route('admin.sidang-ta-guides.edit', $guide))->assertOk()->assertSee('&lt;script&gt;', false);
    $this->get(route('admin.sidang-ta-guides.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->put(route('admin.sidang-ta-guides.update', $guide), array_replace($this->payload, ['title' => 'Diperbarui']))->assertRedirect();
    expect($guide->fresh()->title)->toBe('Diperbarui');
    $this->delete(route('admin.sidang-ta-guides.destroy', $guide))->assertRedirect();
    expect(SidangTaFormGuide::find($guide->id))->toBeNull();
});

test('guide validation rejects unsafe URLs unknown slots bad uploads and excessive sizes', function () {
    $this->actingAs($this->academic);
    foreach (['javascript:alert(1)', 'data:text/html,test', 'ftp://example.test/test'] as $url) {
        $this->post('/dashboard/admin/sidang-ta-guides', array_replace($this->payload, ['url' => $url]))->assertSessionHasErrors('url');
    }
    $this->post('/dashboard/admin/sidang-ta-guides', array_replace($this->payload, ['slot' => 'rogue']))->assertSessionHasErrors('slot');
    $this->post('/dashboard/admin/sidang-ta-guides', $this->payload + ['file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('file');
    $this->post('/dashboard/admin/sidang-ta-guides', $this->payload + ['file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
    expect(SidangTaFormGuide::count())->toBe(0);
});

test('guide files persist privately with replacement and delete cleanup after commit', function () {
    $this->actingAs($this->academic)->post(route('admin.sidang-ta-guides.store'), $this->payload + ['file' => UploadedFile::fake()->image('example.png')])->assertRedirect();
    $guide = SidangTaFormGuide::firstOrFail();
    $old = $guide->file_path;
    expect($old)->toStartWith('sidang-ta/form-guides/');
    Storage::disk('local')->assertExists($old);
    $this->get(route('sidang-ta-guides.media', $guide))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->put(route('admin.sidang-ta-guides.update', $guide), $this->payload + ['file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')])->assertRedirect();
    $new = $guide->fresh()->file_path;
    expect($new)->not->toBe($old);
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($new);
    $this->delete(route('admin.sidang-ta-guides.destroy', $guide))->assertRedirect();
    Storage::disk('local')->assertMissing($new);
});

test('guide replacement cleans new files when persistence fails and preserves the previous file', function () {
    $service = app(SidangTaFormGuideService::class);
    $guide = $service->save(new SidangTaFormGuide, $this->payload, UploadedFile::fake()->image('old.png'));
    $path = $guide->file_path;
    $event = 'eloquent.updating: '.SidangTaFormGuide::class;
    Event::listen($event, fn () => throw new RuntimeException('persistence failure'));
    try {
        expect(fn () => $service->save($guide, $this->payload, UploadedFile::fake()->image('new.png')))->toThrow(RuntimeException::class, 'persistence failure');
    } finally {
        Event::forget($event);
    }
    expect($guide->fresh()->file_path)->toBe($path);
    expect(Storage::disk('local')->allFiles('sidang-ta/form-guides'))->toBe([$path]);
});

test('outer rollback removes new guide files and preserves prior files for replacement and deletion', function () {
    $service = app(SidangTaFormGuideService::class);
    $guide = $service->save(new SidangTaFormGuide, $this->payload, UploadedFile::fake()->image('old.png'));
    $old = $guide->file_path;
    DB::beginTransaction();
    try {
        $updated = $service->save($guide, $this->payload, UploadedFile::fake()->image('new.png'));
        $new = $updated->file_path;
        Storage::disk('local')->assertExists([$old, $new]);
    } finally {
        DB::rollBack();
    }
    expect($guide->fresh()->file_path)->toBe($old);
    Storage::disk('local')->assertExists($old);
    Storage::disk('local')->assertMissing($new);
    DB::beginTransaction();
    try {
        $service->delete($guide->fresh());
        Storage::disk('local')->assertExists($old);
    } finally {
        DB::rollBack();
    }
    expect($guide->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($old);
});

test('guide slots without active guidance show an honest empty cue without example links', function (bool $inactive) {
    if ($inactive) {
        SidangTaFormGuide::create(array_replace($this->payload, ['slot' => 'diploma_biodata', 'is_active' => false]));
    }
    $grouped = app(SidangTaFormGuideService::class)->activeForSlots(['diploma_biodata']);
    $html = view('sidang_ta_guides.partials.guides', ['guides' => $grouped, 'slot' => 'diploma_biodata'])->render();

    expect($html)->toContain('Contoh belum diatur.')->not->toContain('<a ', '<img ');
})->with(['missing guide' => false, 'inactive guide' => true]);

test('active guides are grouped by slot and consistently ordered and the partial escapes descriptions', function () {
    $late = SidangTaFormGuide::create(array_replace($this->payload, ['sort_order' => 9]));
    $early = SidangTaFormGuide::create($this->payload);
    SidangTaFormGuide::create(array_replace($this->payload, ['is_active' => false]));
    SidangTaFormGuide::create(array_replace($this->payload, ['slot' => 'diploma_biodata']));
    $grouped = app(SidangTaFormGuideService::class)->activeForSlots(['ta_data_capture']);
    expect($grouped->keys()->all())->toBe(['ta_data_capture']);
    expect($grouped['ta_data_capture']->pluck('id')->all())->toBe([$early->id, $late->id]);
    $html = view('sidang_ta_guides.partials.guides', ['guides' => $grouped, 'slot' => 'ta_data_capture'])->render();
    expect($html)->toContain('&lt;script&gt;', 'https://example.test/guide')->not->toContain('<script>alert(1)</script>', 'Contoh belum diatur.');
});

test('guide media requires registration permission or Akademik and inactive media is hidden from readers', function () {
    $guide = app(SidangTaFormGuideService::class)->save(new SidangTaFormGuide, $this->payload, UploadedFile::fake()->image('example.png'));
    $this->get(route('sidang-ta-guides.media', $guide))->assertRedirect(route('login'));
    $reader = User::factory()->create(['status' => 'active']);
    $this->actingAs($reader)->get(route('sidang-ta-guides.media', $guide))->assertForbidden();
    foreach (['sidang_ta_registration_read', 'sidang_ta_registration_review'] as $permission) {
        $reader->syncPermissions([$permission]);
        $this->actingAs($reader->fresh())->get(route('sidang-ta-guides.media', $guide))->assertOk();
    }
    $guide->update(['is_active' => false]);
    $this->actingAs($reader->fresh())->get(route('sidang-ta-guides.media', $guide))->assertNotFound();
    $this->actingAs($this->academic)->get(route('sidang-ta-guides.media', $guide))->assertOk();
    Storage::disk('local')->delete($guide->file_path);
    $this->get(route('sidang-ta-guides.media', $guide))->assertNotFound();
});

test('initializer copies actual defaults idempotently without overwriting administrator edits or creating users', function () {
    $count = User::count();
    $this->artisan('sidang-ta:initialize-form-guides')->assertSuccessful();
    $guide = SidangTaFormGuide::where('slot', 'ta_data_capture')->firstOrFail();
    expect(Storage::disk('local')->get($guide->file_path))->toBe(file_get_contents(public_path('assets/images/forms/sidang-ta/ta-data.png')));
    $online = SidangTaFormGuide::where('slot', 'online_supervision_capture')->firstOrFail();
    expect(Storage::disk('local')->get($online->file_path))->toBe(file_get_contents(public_path('assets/images/forms/sidang-ta/bimbingan-online.png')));
    foreach (['eprt_waiver_letter', 'pdm_submission_proof'] as $slot) {
        expect(SidangTaFormGuide::where('slot', $slot)->firstOrFail()->url)->toBe('https://linktr.ee/laa.upps.sby');
    }
    expect(SidangTaFormGuide::whereIn('slot', ['diploma_biodata', 'diploma_statement', 'diploma_biodata_form'])->exists())->toBeFalse();
    $guide->update(['title' => 'Edit admin', 'is_active' => false, 'url' => 'https://example.test/custom']);
    $files = Storage::disk('local')->allFiles();
    $this->artisan('sidang-ta:initialize-form-guides')->assertSuccessful();
    expect(SidangTaFormGuide::count())->toBe(4)->and(User::count())->toBe($count);
    expect($guide->fresh()->title)->toBe('Edit admin')->and($guide->fresh()->is_active)->toBeFalse()->and($guide->fresh()->url)->toBe('https://example.test/custom');
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $this->actingAs($this->academic)->delete(route('admin.sidang-ta-guides.destroy', $online))->assertRedirect();
    $this->artisan('sidang-ta:initialize-form-guides')->assertSuccessful();
    expect(SidangTaFormGuide::where('slot', 'online_supervision_capture')->exists())->toBeFalse();
    expect(SidangTaFormGuide::withTrashed()->count())->toBe(4);
    Storage::disk('local')->assertMissing($online->file_path);
});

test('guide83 delegated role CRUD accepts links images and PDFs', function (string $roleName, string $kind) {
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    foreach (['read', 'create', 'update', 'delete'] as $action) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'sidang_ta_form_guide_'.$action, 'guard_name' => 'web']));
    }
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    $this->actingAs($user);
    $this->get(route('admin.sidang-ta-guides.index'))->assertOk();
    $this->get(route('admin.sidang-ta-guides.create'))->assertOk();
    $this->get(route('dashboard'))->assertSee(route('admin.sidang-ta-guides.index'), false);
    $payload = $this->payload;
    if ($kind === 'image') $payload['file'] = UploadedFile::fake()->image('example.png');
    if ($kind === 'pdf') $payload['file'] = UploadedFile::fake()->create('example.pdf', 10, 'application/pdf');
    $this->post(route('admin.sidang-ta-guides.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $guide = SidangTaFormGuide::firstOrFail();
    expect($guide->url)->toBe('https://example.test/guide');
    if ($kind !== 'link') {
        Storage::disk('local')->assertExists($guide->file_path);
        $this->get(route('sidang-ta-guides.media', $guide))->assertOk();
    }
    $this->get(route('admin.sidang-ta-guides.edit', $guide))->assertOk();
    $this->put(route('admin.sidang-ta-guides.update', $guide), array_replace($payload, ['title' => 'Updated by role']))->assertSessionHasNoErrors()->assertRedirect();
    $this->patch(route('admin.sidang-ta-guides.update', $guide), array_replace($this->payload, ['title' => 'Patched by role']))->assertSessionHasNoErrors()->assertRedirect();
    expect($guide->fresh()->title)->toBe('Patched by role');
    $this->delete(route('admin.sidang-ta-guides.destroy', $guide))->assertRedirect();
    expect(SidangTaFormGuide::find($guide->id))->toBeNull();
})->with([['admin', 'link'], ['admin', 'image'], ['admin', 'pdf'], ['Petugas', 'link']]);

test('guide83 create and update permissions remain independent', function () {
    foreach (['create', 'update'] as $action) Permission::firstOrCreate(['name' => 'sidang_ta_form_guide_'.$action, 'guard_name' => 'web']);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('Petugas');
    $user->givePermissionTo('sidang_ta_form_guide_update');
    $guide = SidangTaFormGuide::create($this->payload);
    $this->actingAs($user);
    $this->post(route('admin.sidang-ta-guides.store'), $this->payload)->assertForbidden();
    $this->put(route('admin.sidang-ta-guides.update', $guide), array_replace($this->payload, ['title' => 'Update only']))->assertSessionHasNoErrors()->assertRedirect();
    $user->syncPermissions(['sidang_ta_form_guide_create']);
    $this->actingAs($user->fresh());
    $this->put(route('admin.sidang-ta-guides.update', $guide), $this->payload)->assertForbidden();
    $this->patch(route('admin.sidang-ta-guides.update', $guide), $this->payload)->assertForbidden();
    $this->delete(route('admin.sidang-ta-guides.destroy', $guide))->assertForbidden();
    $this->post(route('admin.sidang-ta-guides.store'), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
});

test('guide83 storage failures return a file error and preserve existing data', function (bool $replacement, string $failure) {
    $guide = null;
    $path = null;
    $disk = Storage::disk('local');
    if ($replacement) {
        $guide = app(SidangTaFormGuideService::class)->save(new SidangTaFormGuide, $this->payload, UploadedFile::fake()->image('old.png'));
        $path = $guide->file_path;
    }
    $adapter = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
    $write = $adapter->shouldReceive('putFileAs')->once();
    if ($failure === 'mkdir') $write->andThrow(\League\Flysystem\UnableToCreateDirectory::atLocation('/private/server/path', 'Permission denied'));
    elseif ($failure === 'write') $write->andThrow(\League\Flysystem\UnableToWriteFile::atLocation('/private/server/path', 'Permission denied'));
    else $write->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($adapter);
    $payload = array_replace($this->payload, ['title' => 'Failed new title', 'file' => UploadedFile::fake()->image('new.png')]);
    $this->actingAs($this->academic);
    if ($replacement) $response = $this->putJson(route('admin.sidang-ta-guides.update', $guide), $payload);
    else $response = $this->postJson(route('admin.sidang-ta-guides.store'), $payload);
    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    expect($response->getContent())->not->toContain('/private/server/path', 'Permission denied');
    expect(SidangTaFormGuide::count())->toBe($replacement ? 1 : 0);
    if ($replacement) {
        expect($guide->fresh()->title)->toBe('Panduan TA');
        expect($guide->fresh()->file_path)->toBe($path);
        $disk->assertExists($path);
        expect($disk->allFiles('sidang-ta/form-guides'))->toBe([$path]);
    }
})->with([[false, 'mkdir'], [true, 'mkdir'], [false, 'write'], [true, 'write'], [false, 'false'], [true, 'false']]);
