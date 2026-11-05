<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);
});

function multiRoleUser(array $roles): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->syncRoles($roles);

    return $user;
}

test('multi-role user defaults to highest priority role', function () {
    $user = multiRoleUser(['mahasiswa', 'Pelaksana']);

    $this->actingAs($user)->get(route('admin.queue.index'))->assertOk();

    expect(session('active_role'))->toBe('Pelaksana');
    expect($user->assignedRoleNames())->toBe(['Pelaksana', 'mahasiswa']);
});

test('switching to a lower role removes access of the higher role', function () {
    $user = multiRoleUser(['mahasiswa', 'Pelaksana']);

    $this->actingAs($user)
        ->post(route('role.switch'), ['role' => 'mahasiswa'])
        ->assertRedirect(route('dashboard'));

    expect(session('active_role'))->toBe('mahasiswa');

    // Sebagai mahasiswa, halaman operasional antrian harus 403
    $this->actingAs($user)->get(route('admin.queue.index'))->assertForbidden();

    // Kembali ke Pelaksana -> akses pulih
    $this->actingAs($user)
        ->post(route('role.switch'), ['role' => 'Pelaksana'])
        ->assertRedirect(route('dashboard'));
    $this->actingAs($user)->get(route('admin.queue.index'))->assertOk();
});

test('user cannot switch to a role they do not own', function () {
    $user = multiRoleUser(['mahasiswa', 'Pelaksana']);

    $this->actingAs($user)
        ->post(route('role.switch'), ['role' => 'Akademik'])
        ->assertForbidden();
});

test('role switcher is shown only for multi-role users', function () {
    $multi = multiRoleUser(['Petugas', 'Pemimpin']);
    $single = multiRoleUser(['Petugas']);

    $this->actingAs($multi)->get(route('dashboard'))->assertSee('role-switcher', false);
    $this->actingAs($single)->get(route('dashboard'))->assertDontSee('role-switcher', false);
});
