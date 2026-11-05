<?php

use App\Models\User;
use App\Models\Unit;
use Spatie\Permission\Models\Role;

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'dummy-token']),
        '*/issueprofile' => Http::response([
            'user'         => 'krisna_dosen',
            'fullname'     => 'KRISNA DOSEN',
            'email'        => 'krisna_dosen@telkomuniversity.ac.id',
            'phone'        => '08123456789',
            'numberid'     => '987654321',
            'studyprogram' => null,
        ]),
    ]);
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UnitSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);
});

test('superadmin can access pre-assign role page', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();

    $response = $this->actingAs($superadmin)->get(route('user-role.create'));

    $response->assertOk();
    $response->assertSee('Penugasan Hak Akses (Pre-Assignment Pegawai)');
    $response->assertSee('Username SSO / NIP Pegawai');
});

test('superadmin can pre-assign Pelaksana role with unit and position to a non-existent SSO user', function () {
    $superadmin = User::where('email', 'superadmin@example.test')->first();
    $unit = Unit::first();
    $role = Role::where('name', 'Pelaksana')->first();

    $response = $this->actingAs($superadmin)->post(route('user-role.store'), [
        'username' => 'budi_pelaksana@telkomuniversity.ac.id',
        'role_id'  => $role->id,
        'unit_id'  => $unit->id,
        'position' => 'Staf Front Office Layanan',
    ]);

    $response->assertSessionHas('success');

    $user = User::where('username', 'budi_pelaksana')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('Pelaksana'))->toBeTrue();
    expect($user->roleId())->toBe('103');
    expect($user->unit_id)->toBe($unit->id);
    expect($user->position)->toBe('Staf Front Office Layanan');
});

test('pre-assigned role and unit are preserved when user authenticates via SSO login', function () {
    $unit = Unit::first();
    $role = Role::where('name', 'Pelaksana')->first();

    // 1. Superadmin pre-assigns the user
    $user = User::firstOrCreate(
        ['username' => 'krisna_dosen'],
        [
            'name'        => 'krisna_dosen',
            'email'       => 'krisna_dosen@telkomuniversity.ac.id',
            'password'    => bcrypt('initial_random'),
            'auth_source' => 'sso',
            'status'      => 'active',
        ]
    );
    $user->update([
        'unit_id'  => $unit->id,
        'position' => 'Koordinator Pelaksana TA',
    ]);
    $user->syncRoles([$role->name]);

    expect($user->hasRole('Pelaksana'))->toBeTrue();
    expect($user->unit_id)->toBe($unit->id);

    // 2. User logs in via SSO (mock payload for krisna_dosen)
    $response = $this->post('/auth/login-proses', [
        'username' => 'krisna_dosen',
        'password' => 'password',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();

    // 3. Verify user's role and unit are NOT overwritten to default Petugas
    $freshUser = User::where('username', 'krisna_dosen')->first();
    expect($freshUser->hasRole('Pelaksana'))->toBeTrue();
    expect($freshUser->hasRole('Petugas'))->toBeFalse();
    expect($freshUser->unit_id)->toBe($unit->id);
    expect($freshUser->position)->toBe('Koordinator Pelaksana TA');
    // Name and employee_id were synced from SSO profile
    expect($freshUser->name)->toBe('KRISNA DOSEN');
    expect($freshUser->employee_id)->toBe('987654321');
});
