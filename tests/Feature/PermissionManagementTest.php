<?php

use App\Models\User;
use App\Support\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);
    $this->superadmin = User::where('email', 'superadmin@example.test')->first();
});

/** Buat user aktif dengan satu role + permission tambahan di level role. */
function userWithRole(string $role, array $extraPermissions = []): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    if ($extraPermissions) {
        Role::findByName($role)->givePermissionTo($extraPermissions);
    }
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    return $user->fresh();
}

function checkboxTag(string $html, string $permission): ?string
{
    preg_match('/<input[^>]*value="' . preg_quote($permission, '/') . '"[^>]*>/s', $html, $m);

    return $m[0] ?? null;
}

// ─── 1. Tampilan Edit Role ──────────────────────────────────────────────────

test('edit role page shows the same modules and checkboxes for every role', function () {
    $counts = [];
    foreach (['mahasiswa', 'Petugas', 'Pelaksana', 'Pemimpin', 'Pengamat', 'admin'] as $roleName) {
        $role = Role::findByName($roleName);
        $res = $this->actingAs($this->superadmin)->get(route('role.edit', $role))->assertOk();

        foreach (['Sinkronisasi', 'Pengaturan Role', 'Kelola Akun Manual', 'Mahasiswa', 'Dokumen Publik'] as $label) {
            $res->assertSee($label);
        }
        $counts[$roleName] = substr_count($res->getContent(), 'name="permissions[]"');
    }

    expect(array_unique($counts))->toHaveCount(1);
    expect(reset($counts))->toBe(count(PermissionRegistry::allNames()));
});

test('permissions a role does not hold are selectable (not disabled) and held ones are checked', function () {
    $pengamat = Role::findByName('Pengamat'); // read-only
    $html = $this->actingAs($this->superadmin)->get(route('role.edit', $pengamat))->getContent();

    $create = checkboxTag($html, 'course_create');
    $read = checkboxTag($html, 'course_read');

    expect($create)->not->toBeNull()->and($create)->not->toContain('disabled')->and($create)->not->toContain('checked');
    expect($read)->toContain('checked')->and($read)->not->toContain('disabled');
});

test('registry contains no duplicate permission names and all exist in the database', function () {
    $names = PermissionRegistry::allNames();
    expect($names)->toBe(array_values(array_unique($names)));
    foreach ($names as $name) {
        expect(Permission::where('name', $name)->count())->toBe(1);
    }
});

// ─── 2. Simpan permission ───────────────────────────────────────────────────

test('selected permissions are saved and unselected ones are revoked', function () {
    $role = Role::findByName('Pengamat');
    expect($role->hasPermissionTo('course_read'))->toBeTrue();

    $this->actingAs($this->superadmin)
        ->put(route('role.update', $role), ['permissions' => ['course_create', 'sync_read']])
        ->assertRedirect();

    $role->refresh();
    $names = $role->permissions()->pluck('name')->all();
    expect($names)->toContain('course_create', 'sync_read')
        ->and($names)->not->toContain('course_read')
        ->and($names)->toHaveCount(2);

    // Muat ulang halaman: kondisi tercentang sesuai penyimpanan
    $html = $this->actingAs($this->superadmin)->get(route('role.edit', $role))->getContent();
    expect(checkboxTag($html, 'course_create'))->toContain('checked');
    expect(checkboxTag($html, 'course_read'))->not->toContain('checked');
});

test('empty submission revokes all managed permissions but keeps legacy ones outside the registry', function () {
    $role = Role::findByName('Pengamat');
    Permission::firstOrCreate(['name' => 'student_review']);
    $role->givePermissionTo('student_review'); // ada di DB, bukan bagian registry

    $this->actingAs($this->superadmin)->put(route('role.update', $role), [])->assertRedirect();

    $names = $role->refresh()->permissions()->pluck('name')->all();
    expect($names)->toBe(['student_review']);
});

test('backend rejects permission names that are not in the registry', function () {
    $role = Role::findByName('Pengamat');
    Permission::firstOrCreate(['name' => 'rogue_permission']);

    $this->actingAs($this->superadmin)
        ->put(route('role.update', $role), ['permissions' => ['rogue_permission']])
        ->assertSessionHasErrors('permissions.0');

    expect($role->refresh()->hasPermissionTo('rogue_permission'))->toBeFalse();
});

test('Akademik role cannot be edited and keeps its protection', function () {
    $akademik = Role::findByName('Akademik');

    $this->actingAs($this->superadmin)->get(route('role.edit', $akademik))
        ->assertRedirect(route('role.index'));
    $this->actingAs($this->superadmin)->put(route('role.update', $akademik), ['permissions' => []])
        ->assertRedirect(route('role.index'));

    expect($akademik->permissions()->count())->toBeGreaterThan(0);
});

test('IT panel permissions are not given to other roles by default', function () {
    foreach (['admin', 'Pelaksana', 'Pemimpin', 'Pengamat', 'Petugas', 'mahasiswa'] as $roleName) {
        $names = Role::findByName($roleName)->permissions()->pluck('name')->all();
        expect(array_intersect($names, PermissionRegistry::sensitiveNames()))->toBe([]);
    }
    expect(Role::findByName('Akademik')->hasPermissionTo('sync_execute'))->toBeTrue();
});

// ─── 4/5. Route & backend ───────────────────────────────────────────────────

test('user without permission gets 403 on every IT panel endpoint', function () {
    $user = userWithRole('Pengamat'); // read-only operasional, tanpa IT panel
    $role = Role::findByName('Petugas');
    $target = User::factory()->create();

    $this->actingAs($user);
    $this->get(route('role.index'))->assertForbidden();
    $this->get(route('role.edit', $role))->assertForbidden();
    $this->put(route('role.update', $role), ['permissions' => ['course_create']])->assertForbidden();
    $this->post(route('role.assign-user', $role), ['user_id' => $target->id])->assertForbidden();
    $this->post(route('role.revoke-user', $role), ['user_id' => $target->id])->assertForbidden();
    $this->get(route('user-role.create'))->assertForbidden();
    $this->post(route('user-role.store'), ['username' => 'x', 'role_ids' => ['Petugas']])->assertForbidden();

    $this->get(route('manual-users.index'))->assertForbidden();
    $this->get(route('manual-users.create'))->assertForbidden();
    $this->post(route('manual-users.store'), [])->assertForbidden();
    $this->get(route('manual-users.edit', $target))->assertForbidden();
    $this->put(route('manual-users.update', $target), [])->assertForbidden();
    $this->delete(route('manual-users.destroy', $target))->assertForbidden();
    $this->post(route('manual-users.reset-password', $target))->assertForbidden();

    $this->get(route('sync.index'))->assertForbidden();
    $this->get(route('sync.settings'))->assertForbidden();
    $this->put(route('sync.save-settings'), [])->assertForbidden();
    $this->get(route('sync.status'))->assertForbidden();
    $this->get(route('sync.data-stats'))->assertForbidden();
    $this->post(route('sync.scrape-portal'))->assertForbidden();
    $this->post(route('sync.dispatch'))->assertForbidden();
    $this->post(route('sync.import-json'))->assertForbidden();
    $this->post(route('sync.import-academic-profile'))->assertForbidden();
});

test('read permission does not grant create, update or delete', function () {
    $user = userWithRole('Pengamat', ['manual_user_read']);
    $target = User::factory()->create(['auth_source' => 'manual']);

    $this->actingAs($user);
    $this->get(route('manual-users.index'))->assertOk();
    $this->get(route('manual-users.create'))->assertForbidden();
    $this->post(route('manual-users.store'), [])->assertForbidden();
    $this->get(route('manual-users.edit', $target))->assertForbidden();
    $this->put(route('manual-users.update', $target), [])->assertForbidden();
    $this->delete(route('manual-users.destroy', $target))->assertForbidden();
});

test('sync read permission cannot execute synchronization, sync_execute is separate', function () {
    $user = userWithRole('Pengamat', ['sync_read']);

    $this->actingAs($user)->get(route('sync.index'))->assertOk();
    $this->actingAs($user)->post(route('sync.dispatch'))->assertForbidden();
    $this->actingAs($user)->post(route('sync.scrape-portal'))->assertForbidden();
    $this->actingAs($user)->post(route('sync.import-json'))->assertForbidden();
    $this->actingAs($user)->put(route('sync.save-settings'), [])->assertForbidden();

    Role::findByName('Pengamat')->givePermissionTo('sync_execute');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    expect($this->actingAs($user->fresh())->post(route('sync.dispatch'))->status())->not->toBe(403);
    // sync_update tetap terpisah
    $this->actingAs($user->fresh())->put(route('sync.save-settings'), [])->assertForbidden();
});

test('password reset requires both manual_user_update and user_reset_password', function () {
    $target = User::factory()->create(['auth_source' => 'manual']);

    $onlyReset = userWithRole('Pelaksana', ['user_reset_password']);
    $this->actingAs($onlyReset)->post(route('manual-users.reset-password', $target))->assertForbidden();

    $both = userWithRole('Pengamat', ['user_reset_password', 'manual_user_update']);
    expect($this->actingAs($both)->post(route('manual-users.reset-password', $target))->status())->not->toBe(403);
});

test('another role granted IT panel permissions can only use the allowed functions', function () {
    $this->actingAs($this->superadmin)
        ->put(route('role.update', Role::findByName('Pengamat')), ['permissions' => ['role_read']])
        ->assertRedirect();
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('Pengamat');

    $this->actingAs($user)->get(route('role.index'))->assertOk();
    $this->actingAs($user)->get(route('sync.index'))->assertForbidden();
    $this->actingAs($user)->get(route('manual-users.index'))->assertForbidden();
    $this->actingAs($user)->put(route('role.update', Role::findByName('Petugas')), ['permissions' => []])->assertForbidden();
});

test('Akademik keeps full superadmin access to the IT panel', function () {
    $this->actingAs($this->superadmin)->get(route('role.index'))->assertOk();
    $this->actingAs($this->superadmin)->get(route('sync.index'))->assertOk();
    $this->actingAs($this->superadmin)->get(route('manual-users.index'))->assertOk();
    $this->actingAs($this->superadmin)->get(route('user-role.create'))->assertOk();
});

// ─── 5. Anti eskalasi ───────────────────────────────────────────────────────

test('delegated admin cannot edit the role they currently hold', function () {
    $user = userWithRole('Pengamat', ['role_read', 'role_update']);
    $own = Role::findByName('Pengamat');

    $this->actingAs($user)->put(route('role.update', $own), ['permissions' => ['role_read', 'role_update', 'student_delete']])
        ->assertForbidden();
    $this->actingAs($user)->get(route('role.edit', $own))->assertForbidden();

    expect($own->refresh()->hasPermissionTo('student_delete'))->toBeFalse();
});

test('delegated admin can only grant non-sensitive permissions they hold themselves', function () {
    $user = userWithRole('Pengamat', ['role_read', 'role_update', 'student_read']);
    $target = Role::findByName('Petugas');

    $this->actingAs($user)->put(route('role.update', $target), [
        'permissions' => ['student_read', 'student_delete', 'sync_execute', 'role_update'],
    ])->assertRedirect();

    $target->refresh();
    expect($target->hasPermissionTo('student_read'))->toBeTrue();       // dimiliki admin -> boleh
    expect($target->hasPermissionTo('student_delete'))->toBeFalse();    // tidak dimiliki admin
    expect($target->hasPermissionTo('sync_execute'))->toBeFalse();      // sensitif -> hanya Akademik
    expect($target->hasPermissionTo('role_update'))->toBeFalse();
});

test('delegated admin cannot revoke sensitive permissions previously granted by superadmin', function () {
    Role::findByName('Petugas')->givePermissionTo('sync_execute');
    $user = userWithRole('Pengamat', ['role_read', 'role_update']);

    $this->actingAs($user)->put(route('role.update', Role::findByName('Petugas')), ['permissions' => []])->assertRedirect();

    expect(Role::findByName('Petugas')->hasPermissionTo('sync_execute'))->toBeTrue();
});

test('delegated admin cannot assign Akademik, themselves or roles above their authority', function () {
    $user = userWithRole('Pengamat', ['role_assign_user', 'role_revoke_user', 'role_read']);
    $victim = User::factory()->create(['status' => 'active']);
    $akademik = Role::findByName('Akademik');

    // Role Akademik
    $this->actingAs($user)->post(route('role.assign-user', $akademik), ['user_id' => $victim->id])->assertForbidden();
    // Diri sendiri (role Pengamat yang dimiliki)
    $this->actingAs($user)->post(route('role.assign-user', Role::findByName('Pengamat')), ['user_id' => $user->id])->assertForbidden();
    // Role di atas kewenangan (Pelaksana punya permission yang tidak dimiliki Pengamat)
    $this->actingAs($user)->post(route('role.assign-user', Role::findByName('Pelaksana')), ['user_id' => $victim->id])->assertForbidden();
    // Pre-assignment juga dijaga
    $this->actingAs($user)->post(route('user-role.store'), ['username' => $victim->username ?? 'victim', 'role_ids' => ['Akademik']])->assertForbidden();

    expect($victim->refresh()->hasRole('Akademik'))->toBeFalse();
    expect($user->refresh()->hasRole('Akademik'))->toBeFalse();
});

test('delegated admin cannot revoke roles from a superadmin account', function () {
    $user = userWithRole('Pengamat', ['role_revoke_user', 'role_read']);

    $this->actingAs($user)->post(route('role.revoke-user', Role::findByName('Akademik')), ['user_id' => $this->superadmin->id])
        ->assertForbidden();

    expect($this->superadmin->refresh()->hasRole('Akademik'))->toBeTrue();
});

test('delegated admin cannot modify or reset a superadmin manual account', function () {
    $user = userWithRole('Pengamat', ['manual_user_read', 'manual_user_update', 'manual_user_delete', 'user_reset_password']);

    $this->actingAs($user)->get(route('manual-users.edit', $this->superadmin))->assertForbidden();
    $this->actingAs($user)->delete(route('manual-users.destroy', $this->superadmin))->assertForbidden();
    $this->actingAs($user)->post(route('manual-users.reset-password', $this->superadmin))->assertForbidden();
});

// ─── 6. Sidebar ─────────────────────────────────────────────────────────────

test('sidebar shows IT panel menus only according to permissions', function () {
    $user = userWithRole('Pengamat', ['sync_read']);

    $res = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $res->assertSee('Sinkronisasi')->assertDontSee('Pengaturan Role')->assertDontSee('Kelola Akun Manual');

    Role::findByName('Pengamat')->givePermissionTo('role_read');
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertSee('Pengaturan Role')->assertDontSee('Kelola Akun Manual');
});

test('Akademik sees every IT panel menu in the sidebar', function () {
    $this->actingAs($this->superadmin)->get(route('dashboard'))
        ->assertSee('Sinkronisasi')->assertSee('Pengaturan Role')->assertSee('Kelola Akun Manual');
});

test('Dokumen menu and routes follow the document_read permission', function () {
    $without = userWithRole('mahasiswa');
    $with = userWithRole('Pengamat'); // punya document_read

    $this->actingAs($without)->get(route('dashboard'))->assertOk();
    $this->actingAs($without)->get(route('documents.index'))->assertForbidden();
    $this->actingAs($with)->get(route('documents.index'))->assertOk();
    $this->actingAs($with)->get(route('dashboard'))->assertSee('Dokumen');
});

test('yudisium admin delete and document review are guarded by their own permissions', function () {
    // (Route model binding berjalan sebelum middleware, jadi middleware route diperiksa langsung.)
    $middlewareOf = fn (string $name) => app('router')->getRoutes()->getByName($name)->gatherMiddleware();

    expect($middlewareOf('admin.graduation-registrations.destroy'))->toContain('permission:graduation_registration_delete');
    expect($middlewareOf('graduation-registration-documents.review'))->toContain('permission:graduation_registration_review');
    expect($middlewareOf('graduation-registration-documents.review'))->not->toContain('permission:graduation_registration_update');

    // Mahasiswa punya _update tetapi bukan _review: tidak lolos gerbang review.
    expect(Role::findByName('mahasiswa')->hasPermissionTo('graduation_registration_review'))->toBeFalse();
    expect(Role::findByName('Petugas')->hasPermissionTo('graduation_registration_delete'))->toBeFalse();
});