<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Seed roles and users
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);
});

test('superadmin user has role Akademik and isSuperAdmin helper returns true', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();

    expect($superadmin)->not->toBeNull();
    expect($superadmin->hasRole('Akademik'))->toBeTrue();
    expect($superadmin->isSuperAdmin())->toBeTrue();
    expect($superadmin->isManualAccount())->toBeTrue();
});

test('superadmin has bypass access via Gate::before for all abilities', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();

    expect(Gate::forUser($superadmin)->allows('any_random_permission_xyz'))->toBeTrue();
    expect(Gate::forUser($superadmin)->allows('graduation_registration_review'))->toBeTrue();
    expect(Gate::forUser($superadmin)->allows('document_delete'))->toBeTrue();
});

test('superadmin can access IT panel routes (role, sync, manual users)', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();

    $this->actingAs($superadmin)
        ->get(route('role.index'))
        ->assertOk();

    $this->actingAs($superadmin)
        ->get(route('sync.index'))
        ->assertOk();

    $this->actingAs($superadmin)
        ->get(route('manual-users.index'))
        ->assertOk();
});

test('admin user has role admin, isSuperAdmin is false, and default permissions exclude review', function () {
    $admin = User::where('email', 'admin@example.test')->first();

    expect($admin)->not->toBeNull();
    expect($admin->hasRole('admin'))->toBeTrue();
    expect($admin->isSuperAdmin())->toBeFalse();
    expect($admin->isManualAccount())->toBeTrue();

    // Admin has CRUD permissions
    expect($admin->can('course_create'))->toBeTrue();
    expect($admin->can('document_read'))->toBeTrue();

    // Admin does NOT have review permissions by default
    expect($admin->can('graduation_registration_review'))->toBeFalse();
    expect($admin->can('grade_appeal_review'))->toBeFalse();
});

test('admin user cannot access IT panel routes', function () {
    $admin = User::where('email', 'admin@example.test')->first();

    $this->actingAs($admin)
        ->get(route('role.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('sync.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('manual-users.index'))
        ->assertForbidden();
});

test('role management protects Akademik from being edited', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();

    $akademik = Role::findByName('Akademik');

    // Attempting to edit Akademik
    $this->actingAs($superadmin)
        ->get(route('role.edit', $akademik))
        ->assertRedirect(route('role.index'))
        ->assertSessionHas('error');

    // Attempting to update Akademik
    $this->actingAs($superadmin)
        ->put(route('role.update', $akademik), ['permissions' => ['course_read']])
        ->assertRedirect(route('role.index'))
        ->assertSessionHas('error');
});

test('superadmin can view and update admin role permissions via role management', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();
    $adminRole = Role::findByName('admin');
    $adminUser = User::where('email', 'admin@example.test')->first();

    // Superadmin opens edit page for admin role
    $this->actingAs($superadmin)
        ->get(route('role.edit', $adminRole))
        ->assertOk();

    // Initial check: admin cannot review graduation
    expect($adminUser->can('graduation_registration_review'))->toBeFalse();

    // Superadmin grants graduation_registration_review to admin role
    $newPermissions = ['course_read', 'graduation_registration_review'];

    $this->actingAs($superadmin)
        ->put(route('role.update', $adminRole), [
            'permissions' => $newPermissions,
        ])
        ->assertRedirect(route('role.index'))
        ->assertSessionHas('success');

    // Refresh role permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $freshAdminUser = User::where('email', 'admin@example.test')->first();
    expect($freshAdminUser->can('graduation_registration_review'))->toBeTrue();
    expect($freshAdminUser->can('course_read'))->toBeTrue();
    // Permissions not in the list should now be revoked
    expect($freshAdminUser->can('course_create'))->toBeFalse();
});
