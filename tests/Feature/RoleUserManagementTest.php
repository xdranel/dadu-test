<?php

use App\Models\User;
use App\Models\Unit;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UnitSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);

    $this->superadmin = User::where('email', 'superadmin@example.test')->first();
});

test('superadmin can access edit role and view tab kelola pengguna with assignment form and search box', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    $response = $this->actingAs($this->superadmin)->get(route('role.edit', $role));

    $response->assertOk();
    $response->assertSee('Tugaskan Pengguna ke Role Mahasiswa');
    $response->assertSee('Pengguna dengan Role');
    $response->assertSee('name="search"', false);
    $response->assertSee('Cari');
});

test('mahasiswa role does not load student accounts by default without search', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    // Buat mahasiswa contoh
    $student = User::factory()->create([
        'username'    => '103072400999',
        'name'        => 'Mahasiswa Khusus Testing',
        'email'       => 'mhs_testing@telkomuniversity.ac.id',
        'status'      => 'active',
        'auth_source' => 'sso',
    ]);
    $student->assignRole('mahasiswa');

    $response = $this->actingAs($this->superadmin)->get(route('role.edit', $role));

    $response->assertOk();
    $response->assertSee('Cari Mahasiswa Terlebih Dahulu');
    $response->assertSee('Pencarian diperlukan');
    $response->assertDontSee('Mahasiswa Khusus Testing');
});

test('search works by username, name, email for role mahasiswa', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    $student = User::factory()->create([
        'username'    => '103072400888',
        'name'        => 'Budi Mahasiswa Unik',
        'email'       => 'budi_unik@telkomuniversity.ac.id',
        'status'      => 'active',
        'auth_source' => 'sso',
    ]);
    $student->assignRole('mahasiswa');

    // 1. Cari by username
    $resUsername = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $role, 'search' => '103072400888']));
    $resUsername->assertOk();
    $resUsername->assertSee('Budi Mahasiswa Unik');
    $resUsername->assertSee('103072400888');

    // 2. Cari by nama
    $resName = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $role, 'search' => 'Budi Mahasiswa']));
    $resName->assertOk();
    $resName->assertSee('Budi Mahasiswa Unik');

    // 3. Cari by email
    $resEmail = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $role, 'search' => 'budi_unik@']));
    $resEmail->assertOk();
    $resEmail->assertSee('Budi Mahasiswa Unik');
});

test('search only displays users of the role being edited and does not leak other roles', function () {
    $mhsRole = Role::where('name', 'mahasiswa')->first();
    $petugasRole = Role::where('name', 'Petugas')->first();

    $mhsUser = User::factory()->create([
        'username'    => 'krisna_mhs_secret',
        'name'        => 'Krisna Mahasiswa Akun',
        'email'       => 'krisna_mhs_secret@telkomuniversity.ac.id',
        'status'      => 'active',
    ]);
    $mhsUser->assignRole('mahasiswa');

    $petugasUser = User::factory()->create([
        'username'    => 'krisna_petugas_secret',
        'name'        => 'Krisna Petugas Akun',
        'email'       => 'krisna_petugas_secret@telkomuniversity.ac.id',
        'status'      => 'active',
    ]);
    $petugasUser->assignRole('Petugas');

    // Cari 'krisna' di role Mahasiswa -> HANYA mhsUser yang muncul
    $resMhs = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $mhsRole, 'search' => 'krisna']));
    $resMhs->assertOk();
    $resMhs->assertSee('Krisna Mahasiswa Akun');
    $resMhs->assertDontSee('Krisna Petugas Akun');

    // Cari 'krisna' di role Petugas -> HANYA petugasUser yang muncul
    $resPetugas = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $petugasRole, 'search' => 'krisna']));
    $resPetugas->assertOk();
    $resPetugas->assertSee('Krisna Petugas Akun');
    $resPetugas->assertDontSee('Krisna Mahasiswa Akun');
});

test('search shows clear empty state when no results found and provides reset button', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    $response = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $role, 'search' => 'nim_tidak_ditemukan_xyz']));

    $response->assertOk();
    $response->assertSee('Pengguna Tidak Ditemukan');
    $response->assertSee('Reset');
});

test('pagination preserves search query string', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    // Buat 15 mahasiswa dengan prefix sama
    for ($i = 1; $i <= 15; $i++) {
        $u = User::factory()->create([
            'username' => 'searchpage_' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'name'     => 'Search Page Student ' . $i,
            'email'    => "searchpage_{$i}@telkomuniversity.ac.id",
            'status'   => 'active',
        ]);
        $u->assignRole('mahasiswa');
    }

    $response = $this->actingAs($this->superadmin)->get(route('role.edit', ['role' => $role, 'search' => 'searchpage']));

    $response->assertOk();
    $response->assertSee('15 pengguna');
    // Cek bahwa link pagination membawa query string ?search=searchpage
    $response->assertSee('search=searchpage');
});

test('form assign user remains working and gives role to user', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    $response = $this->actingAs($this->superadmin)->post(route('role.assign-user', $role), [
        'username' => 'mhs_baru_assign',
    ]);

    $response->assertSessionHas('success');
    $assignedUser = User::where('username', 'mhs_baru_assign')->first();
    expect($assignedUser)->not->toBeNull();
    expect($assignedUser->hasRole('mahasiswa'))->toBeTrue();
});

test('tombol Nonaktifkan benar-benar mengubah status akun menjadi inactive', function () {
    $role = Role::where('name', 'mahasiswa')->first();

    $student = User::factory()->create([
        'username' => 'student_deactivate_test',
        'name'     => 'Student Deactivate Test',
        'status'   => 'active',
    ]);
    $student->assignRole('mahasiswa');

    // Nonaktifkan
    $response = $this->actingAs($this->superadmin)->post(route('role.revoke-user', $role), [
        'user_id' => $student->id,
    ]);

    $response->assertSessionHas('success');
    expect($student->fresh()->status)->toBe('inactive');

    // Aktifkan kembali
    $responseActive = $this->actingAs($this->superadmin)->post(route('role.revoke-user', $role), [
        'user_id' => $student->id,
    ]);

    $responseActive->assertSessionHas('success');
    expect($student->fresh()->status)->toBe('active');
});

test('akun nonaktif ditolak saat login SSO', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'dummy-token']),
        '*/issueprofile' => Http::response([
            'user'         => 'student_inactive_sso',
            'fullname'     => 'Student Inactive SSO',
            'email'        => 'student_inactive_sso@telkomuniversity.ac.id',
            'numberid'     => '103072400999',
            'studyprogram' => 'S1 Informatika',
        ]),
    ]);

    $inactiveUser = User::factory()->create([
        'username'    => 'student_inactive_sso',
        'status'      => 'inactive',
        'auth_source' => 'sso',
    ]);
    $inactiveUser->assignRole('mahasiswa');

    $response = $this->post(route('sso.api.login'), [
        'username'             => 'student_inactive_sso',
        'password'             => 'secret123',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
    expect($inactiveUser->fresh()->status)->toBe('inactive');
});

test('akun nonaktif tidak dapat melanjutkan akses menggunakan session lama', function () {
    $user = User::factory()->create([
        'username' => 'user_kicked_test',
        'status'   => 'inactive',
    ]);
    $user->assignRole('mahasiswa');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('pengguna tanpa otorisasi tidak dapat mengubah status melalui request langsung', function () {
    $role = Role::where('name', 'mahasiswa')->first();
    $regularUser = User::factory()->create(['status' => 'active']);
    $regularUser->assignRole('mahasiswa');

    $target = User::factory()->create(['status' => 'active']);
    $target->assignRole('mahasiswa');

    $response = $this->actingAs($regularUser)->post(route('role.revoke-user', $role), [
        'user_id' => $target->id,
    ]);

    $response->assertForbidden();
    expect($target->fresh()->status)->toBe('active');
});

test('role Akademik tetap terlindungi dari deactivation', function () {
    $akademikRole = Role::where('name', 'Akademik')->first();

    // Superadmin tidak bisa nonaktifkan diri sendiri
    $responseSelf = $this->actingAs($this->superadmin)->post(route('role.revoke-user', $akademikRole), [
        'user_id' => $this->superadmin->id,
    ]);

    $responseSelf->assertSessionHas('error');
    expect($this->superadmin->fresh()->status)->toBe('active');
});

test('unit kerja dropdown in edit role displays units from UnitSeeder', function () {
    $pelaksanaRole = Role::where('name', 'Pelaksana')->first();

    $response = $this->actingAs($this->superadmin)->get(route('role.edit', $pelaksanaRole));

    $response->assertOk();
    $response->assertSee('-- Pilih Unit Kerja --');

    foreach (\Database\Seeders\UnitSeeder::UNITS as $unitData) {
        $response->assertSee($unitData['name']);
        if (!empty($unitData['code'])) {
            $response->assertSee("({$unitData['code']})");
        }
    }
});
