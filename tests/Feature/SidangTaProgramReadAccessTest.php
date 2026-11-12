<?php

use App\Models\Lecturer;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    foreach (['sidang_ta_registration_view_program', 'sidang_ta_registration_review', 'sidang_ta_registration_read'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->viewer = User::factory()->create(['email' => 'dosen@kampus.test', 'username' => 'dosen105',
        'status' => 'active', 'auth_source' => 'sso', 'must_change_password' => false]);
    $role = Role::findOrCreate('Pelaksana', 'web');
    $role->syncPermissions(['sidang_ta_registration_view_program', 'sidang_ta_registration_read']);
    $this->viewer->assignRole($role);
    $this->lecturer = Lecturer::create(['name' => 'Dosen Uji', 'lecturer_code' => 'D105',
        'email_domain' => ' DOSEN@KAMPUS.TEST ', 'study_program' => 'Informatika']);
    $this->period = SidangTaPeriod::create(['name' => 'Uji Akses Prodi', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->makeRegistration = function (string $number, string $program) {
        return SidangTaRegistration::create(['sidang_ta_period_id' => $this->period->id,
            'student_number' => $number, 'student_name' => 'Mahasiswa '.$number, 'study_program' => $program,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(), 'review_notes' => 'Catatan petugas tetap dibaca',
            'academic_snapshot' => ['admission' => ['eprt' => ['mode' => 'automatic']]]])->fresh();
    };
    $this->own = ($this->makeRegistration)('105001', 'S1 Informatika - Kampus Surabaya');
    $this->other = ($this->makeRegistration)('105002', 'S1 Sistem Informasi - Kampus Surabaya');
    $this->attach = function ($registration) {
        $path = 'sidang_ta/105/'.$registration->id.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 contoh');
        return $registration->documents()->create(['document_type' => 'ta_data_capture',
            'file_name' => 'uji.pdf', 'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16,
            'status' => 'rejected', 'notes' => 'Perbaiki halaman pengesahan']);
    };
    $this->document = ($this->attach)($this->own)->fresh();
    $this->foreignDocument = ($this->attach)($this->other);
    $this->own->logs()->create(['action' => 'needs_review', 'notes' => 'Riwayat revisi tetap dibaca', 'performed_at' => now()]);
    $this->actingAs($this->viewer);
});

test('program reader sees only their program counts and no editable program filter or export', function () {
    $response = $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 1)
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $this->own->id)
        ->assertSee('105001')->assertDontSee('105002');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('select[name="study_program"]')->count())->toBe(0)
        ->and($crawler->filter('[data-ta-program-scope]')->text())->toContain('Informatika')
        ->and($crawler->filter('[data-ta-export], [data-ta-export-empty]')->count())->toBe(0)
        ->and($crawler->filter('a[data-ta-doc]')->count())->toBeGreaterThan(0);
    expect($crawler->filter('aside a')->reduce(fn ($n) => $n->attr('href') === route('admin.sidang-ta-registrations.index'))->count())->toBe(1);
});

test('forged program filter and OR search cannot expand lecturer scope', function () {
    $this->get(route('admin.sidang-ta-registrations.index', ['study_program' => $this->other->study_program]))
        ->assertOk()->assertViewHas('counts', fn ($c) => $c['total'] === 1)->assertDontSee('105002');
    $this->get(route('admin.sidang-ta-registrations.index', ['search' => '105002']))
        ->assertOk()->assertViewHas('counts', fn ($c) => $c['total'] === 0)->assertDontSee('105002</p>', false);
});

test('reader sees document notes history and previews but no review or decision inputs', function () {
    $html = $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertOk()
        ->assertSee('Catatan petugas tetap dibaca')->assertSee('Perbaiki halaman pengesahan')
        ->assertSee('Riwayat revisi tetap dibaca')->getContent();
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-doc-review-form], textarea[name="notes"], textarea[name="review_notes"]')->count())->toBe(0)
        ->and($crawler->filter('[data-document-preview]')->count())->toBe(1);
    $this->get(route('sidang-ta-registration-documents.preview', $this->document))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $this->document))->assertOk();
});

test('reader cannot open another program through any detail or document URL', function () {
    foreach (['admin.sidang-ta-registrations.show', 'sidang-ta-registrations.show'] as $route) {
        $this->get(route($route, $this->other))->assertForbidden();
    }
    foreach (['preview', 'download'] as $action) {
        $this->get(route('sidang-ta-registration-documents.'.$action, $this->foreignDocument))->assertForbidden();
    }
});

test('read only permission rejects all review posts without changing data', function () {
    $before = $this->own->getRawOriginal();
    $documentBefore = $this->document->getRawOriginal();
    foreach (['approved', 'rejected', 'needs_review'] as $action) {
        $this->post(route('admin.sidang-ta-registrations.review', $this->own), [
            'action' => $action, 'review_notes' => 'Percobaan', 'registration_version' => 1])->assertForbidden();
    }
    $this->post(route('admin.sidang-ta-registrations.documents.review', [$this->own, $this->document]), [
        'status' => 'verified', 'notes' => 'Percobaan', 'registration_version' => 1])->assertForbidden();
    $this->get(route('admin.sidang-ta-registrations.export'))->assertForbidden();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
    expect($this->own->fresh()->getRawOriginal())->toBe($before)
        ->and($this->document->fresh()->getRawOriginal())->toBe($documentBefore)
        ->and($this->own->logs()->count())->toBe(1);
});

test('same Pelaksana permission does not grant non lecturers access', function () {
    $this->lecturer->delete();
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertForbidden();
    $this->get(route('sidang-ta-registration-documents.preview', $this->document))->assertForbidden();
});

test('missing ambiguous or empty lecturer identity fails closed', function (string $mode) {
    if ($mode === 'duplicate') Lecturer::create(['name' => 'Duplikat', 'lecturer_code' => 'DU105',
        'email_domain' => 'dosen@kampus.test', 'study_program' => 'Informatika']);
    if ($mode === 'program') $this->lecturer->update(['study_program' => '  ']);
    if ($mode === 'email') $this->viewer->forceFill(['email' => ''])->save();
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertForbidden();
})->with(['duplicate', 'program', 'email']);

test('lecturer program changes apply next request without changing SSO or registration snapshots', function () {
    $before = $this->own->getRawOriginal();
    $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertOk();
    $this->lecturer->update(['study_program' => 'Sistem Informasi']);
    $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertForbidden();
    $this->get(route('admin.sidang-ta-registrations.show', $this->other))->assertOk();
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $this->other->id);
    expect($this->own->fresh()->getRawOriginal())->toBe($before);
});

test('program matching uses complete approved labels not substrings or another campus or degree', function () {
    ($this->makeRegistration)('105003', 'S1 Teknik Informatika - Kampus Surabaya');
    ($this->makeRegistration)('105004', 'S1 Informatika - Kampus Bandung');
    ($this->makeRegistration)('105005', 'S2 Informatika - Kampus Surabaya');
    ($this->makeRegistration)('105006', ' INFORMATIKA ');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('counts', fn ($c) => $c['total'] === 2);
});

test('full reviewer retains program filter and unscoped access', function () {
    $this->viewer->givePermissionTo('sidang_ta_registration_review');
    $this->lecturer->delete();
    $html = $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('counts', fn ($c) => $c['total'] === 2)->getContent();
    expect((new Crawler($html))->filter('select[name="study_program"]')->count())->toBe(1);
    $html = $this->get(route('admin.sidang-ta-registrations.show', $this->other))->assertOk()->getContent();
    expect((new Crawler($html))->filter('[data-doc-review-form]')->count())->toBe(1);
});

test('new custom action appears without replacing existing deployed role permissions', function () {
    $role = Role::findByName('Pelaksana');
    $role->syncPermissions(['sidang_ta_registration_read', 'sidang_ta_registration_review']);
    PermissionRegistry::ensureExist();
    expect(PermissionRegistry::modules()['sidang_ta_registration']['actions']['view_program']['custom'] ?? false)->toBeTrue()
        ->and($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['sidang_ta_registration_read', 'sidang_ta_registration_review']);
});


test('special action alone opens scoped final documents but not drafts or student actions', function () {
    Role::findByName('Pelaksana')->syncPermissions(['sidang_ta_registration_view_program']);
    $this->viewer->unsetRelation('roles');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $this->get(route('sidang-ta-registration-documents.preview', $this->document))->assertOk();
    $this->get(route('sidang-ta-registration-documents.download', $this->document))->assertOk();
    $this->get(route('sidang-ta-registrations.create'))->assertForbidden();
});

test('read permission alone cannot open staff review pages', function () {
    Role::findByName('Pelaksana')->syncPermissions(['sidang_ta_registration_read']);
    $this->viewer->unsetRelation('roles');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-registrations.show', $this->own))->assertForbidden();
});

test('Digital Bisnis lecturer spelling matches only Bisnis Digital program', function () {
    $this->lecturer->update(['study_program' => 'Digital Bisnis']);
    $target = ($this->makeRegistration)('105007', 'S1 Bisnis Digital - Kampus Surabaya');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
});

test('active Pelaksana role does not inherit reviewer rights from another assigned role', function () {
    $role = Role::findOrCreate('Petugas', 'web');
    $role->syncPermissions(['sidang_ta_registration_review']);
    $this->viewer->assignRole($role);
    $this->withSession(['active_role' => 'Pelaksana'])->get(route('admin.sidang-ta-registrations.show', $this->other))->assertForbidden();
    $this->withSession(['active_role' => 'Pelaksana'])->post(route('admin.sidang-ta-registrations.review', $this->own), [
        'action' => 'approved', 'registration_version' => 1])->assertForbidden();
    $this->withSession(['active_role' => 'Petugas'])->get(route('admin.sidang-ta-registrations.show', $this->other))->assertOk();
});

test('fresh seed gives Pelaksana scoped viewing without TA review or period management', function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $role = Role::findByName('Pelaksana');
    expect($role->hasPermissionTo('sidang_ta_registration_view_program'))->toBeTrue()
        ->and($role->hasPermissionTo('sidang_ta_registration_review'))->toBeFalse()
        ->and($role->hasPermissionTo('sidang_ta_registration_create'))->toBeFalse()
        ->and($role->hasPermissionTo('sidang_ta_registration_update'))->toBeFalse()
        ->and($role->hasPermissionTo('sidang_ta_period_read'))->toBeFalse();
    expect(Role::findByName('mahasiswa')->hasPermissionTo('sidang_ta_registration_view_program'))->toBeFalse()
        ->and(Role::findByName('admin')->hasPermissionTo('sidang_ta_registration_view_program'))->toBeFalse()
        ->and(Role::findByName('Petugas')->hasPermissionTo('sidang_ta_registration_review'))->toBeTrue();
});


test('accent insensitive DB collation cannot link a different email identity', function () {
    $this->lecturer->update(['email_domain' => 'dósen@kampus.test']);
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
});

test('accent different email does not make an exact lecturer identity ambiguous', function () {
    Lecturer::create(['name' => 'Identitas berbeda', 'lecturer_code' => 'ACC105',
        'email_domain' => 'dósen@kampus.test', 'study_program' => 'Sistem Informasi']);
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('counts', fn ($c) => $c['total'] === 1);
});

test('program list and detail both exclude labels outside approved normalization', function () {
    $accented = ($this->makeRegistration)('105008', 'S1 Informátika - Kampus Surabaya');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('counts', fn ($c) => $c['total'] === 1)->assertDontSee('105008');
    $this->get(route('admin.sidang-ta-registrations.show', $accented))->assertForbidden();
});

test('distinct program spelling preserves valid labels even if DB collation groups them', function () {
    $this->own->update(['study_program' => 'S1 Informátika - Kampus Surabaya']);
    $valid = ($this->makeRegistration)('105009', 'S1 Informatika - Kampus Surabaya');
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk()
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $valid->id);
});

test('scoped pagination and combined filters never count another program', function () {
    foreach (range(10, 27) as $id) ($this->makeRegistration)('105'.$id, 'Informatika');
    $this->other->update(['status' => 'approved']);
    $this->get(route('admin.sidang-ta-registrations.index', ['page' => 2, 'period' => $this->period->id, 'status' => 'submitted']))
        ->assertOk()->assertViewHas('counts', fn ($c) => $c['total'] === 19 && $c['approved'] === 0)
        ->assertViewHas('registrations', fn ($rows) => $rows->total() === 19 && $rows->count() === 4);
});


test('program reading does not expose another students draft or its private documents', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $draft = \App\Models\SidangTaRegistrationDraft::create(['owner_user_id' => $owner->id,
        'student_number' => $this->own->student_number, 'sidang_ta_period_id' => $this->period->id]);
    $document = $draft->documents()->create(['document_type' => 'ta_data_capture', 'file_name' => 'draft.pdf',
        'file_path' => 'private/draft.pdf', 'file_size' => 16, 'file_type' => 'pdf']);
    Storage::disk('local')->put('private/draft.pdf', '%PDF-1.4 draft');
    $this->get(route('sidang-ta-drafts.edit', $draft))->assertForbidden();
    foreach (['preview', 'download'] as $action) {
        $this->get(route('sidang-ta-draft-documents.'.$action, $document))->assertForbidden();
    }
});
