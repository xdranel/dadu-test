<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request as HttpRequest;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['app.env' => 'testing']);
    config(['services.recaptcha.enabled' => true]);
    Http::preventStrayRequests();

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'UnitSeeder']);
    $this->artisan('db:seed', ['--class' => 'UserSeeder']);

    $this->superadmin = User::where('email', 'superadmin@example.test')->first();
});

test('pegawai with @telkomuniversity.ac.id lands on Pelaksana as default role on first SSO login', function () {
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'pegawai-token']),
        '*/issueprofile' => Http::response([
            'user'         => 'pegawai_baru',
            'fullname'     => 'Budi Pegawai Baru',
            'email'        => 'pegawai_baru@telkomuniversity.ac.id',
            'phone'        => '081234567890',
            'numberid'     => '198501012010',
            'studyprogram' => null, // Pegawai tidak memiliki studyprogram
        ]),
    ]);

    $response = $this->post(route('sso.api.login'), [
        'username'             => 'pegawai_baru@telkomuniversity.ac.id',
        'password'             => 'password123',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = User::where('username', 'pegawai_baru')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('pegawai_baru@telkomuniversity.ac.id');
    expect($user->hasRole('Pelaksana'))->toBeTrue();
    expect($user->hasRole('Petugas'))->toBeFalse();
    expect($user->sso_role)->toBe('Pelaksana');
});

test('admin can assign student with @student.telkomuniversity.ac.id to Petugas role', function () {
    $petugasRole = Role::where('name', 'Petugas')->first();

    // Buat mahasiswa di database
    $student = Student::create([
        'student_number' => '1301190001',
        'name'           => 'Mahasiswa Teladan',
        'email'          => '1301190001@student.telkomuniversity.ac.id',
        'academic_year'  => '2019',
        'study_program'  => 'Informatika',
        'status'         => 'Aktif',
    ]);

    $response = $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => '1301190001@student.telkomuniversity.ac.id',
    ]);

    $response->assertSessionHas('success');

    $user = User::where('username', '1301190001')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Mahasiswa Teladan');
    expect($user->email)->toBe('1301190001@student.telkomuniversity.ac.id');
    expect($user->hasRole('Petugas'))->toBeTrue();
    expect($user->hasRole('mahasiswa'))->toBeTrue();
});

test('admin can assign existing student user to Petugas role without being blocked', function () {
    $petugasRole = Role::where('name', 'Petugas')->first();

    $studentUser = User::create([
        'username'    => '1301190002',
        'name'        => 'Mahasiswa Dua',
        'email'       => '1301190002@student.telkomuniversity.ac.id',
        'password'    => bcrypt('password'),
        'auth_source' => 'sso',
        'status'      => 'active',
    ]);
    $studentUser->assignRole('mahasiswa');

    $response = $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => '1301190002',
    ]);

    $response->assertSessionHas('success');

    $studentUser->refresh();
    expect($studentUser->hasRole('Petugas'))->toBeTrue();
    expect($studentUser->hasRole('mahasiswa'))->toBeTrue();
});

test('student assigned to Petugas role logs in and lands on Petugas role with access', function () {
    // Daftarkan mahasiswa ke role Petugas terlebih dahulu
    $petugasRole = Role::where('name', 'Petugas')->first();
    $student = Student::create([
        'student_number' => '1301190003',
        'name'           => 'Mahasiswa Petugas',
        'email'          => '1301190003@student.telkomuniversity.ac.id',
        'academic_year'  => '2019',
        'study_program'  => 'Informatika',
        'status'         => 'Aktif',
    ]);

    $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => '1301190003@student.telkomuniversity.ac.id',
    ]);

    // Sekarang simulasikan login SSO mahasiswa tersebut
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'student-token']),
        '*/issueprofile' => Http::response([
            'user'         => '1301190003',
            'fullname'     => 'Mahasiswa Petugas',
            'email'        => '1301190003@student.telkomuniversity.ac.id',
            'phone'        => '081234567891',
            'numberid'     => '1301190003',
            'studyprogram' => 'Informatika',
        ]),
    ]);

    $response = $this->post(route('sso.api.login'), [
        'username'             => '1301190003',
        'password'             => 'password123',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');

    $user = User::where('username', '1301190003')->first();
    expect($user->hasRole('Petugas'))->toBeTrue();
    expect($user->hasRole('mahasiswa'))->toBeTrue();

    // Pastikan prioritas role menempatkan Petugas di urutan pertama (landing role aktif)
    expect($user->assignedRoleNames()[0])->toBe('Petugas');
});

test('assigning student with invalid email format or invalid domain shows clear error', function () {
    $petugasRole = Role::where('name', 'Petugas')->first();

    // 1. Format email tidak valid
    $resFormat = $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => 'invalid-email@@domain',
    ]);
    $resFormat->assertSessionHas('error', 'Format email SSO tidak valid.');

    // 2. Domain bukan telkomuniversity
    $resDomain = $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => 'mahasiswa@gmail.com',
    ]);
    $resDomain->assertSessionHas('error', 'Domain email tidak valid. Gunakan akun SSO dengan domain @telkomuniversity.ac.id atau @student.telkomuniversity.ac.id.');
});

test('assigning student not registered in database shows clear error', function () {
    $petugasRole = Role::where('name', 'Petugas')->first();

    $response = $this->actingAs($this->superadmin)->post(route('role.assign-user', $petugasRole), [
        'username' => '9999999999@student.telkomuniversity.ac.id',
    ]);

    $response->assertSessionHas('error', 'Akun mahasiswa tidak ditemukan di database. Pastikan NIM atau email mahasiswa telah terdaftar.');
});

test('pre-assigned roles for manual users are never overwritten on SSO login', function () {
    $adminRole = Role::where('name', 'admin')->first();

    // Pegawai yang sudah di-assign manual role admin oleh Superadmin
    $user = User::create([
        'username'    => 'admin_khusus',
        'name'        => 'Admin Khusus',
        'email'       => 'admin_khusus@telkomuniversity.ac.id',
        'password'    => bcrypt('password'),
        'auth_source' => 'sso',
        'status'      => 'active',
    ]);
    $user->assignRole('admin');

    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        '*/issueauth' => Http::response(['token' => 'admin-token']),
        '*/issueprofile' => Http::response([
            'user'         => 'admin_khusus',
            'fullname'     => 'Admin Khusus SSO',
            'email'        => 'admin_khusus@telkomuniversity.ac.id',
            'phone'        => '081234567899',
            'numberid'     => '12345678',
            'studyprogram' => null,
        ]),
    ]);

    $response = $this->post(route('sso.api.login'), [
        'username'             => 'admin_khusus',
        'password'             => 'password123',
        'g-recaptcha-response' => 'mock-token',
    ]);

    $this->assertAuthenticated();
    $freshUser = User::where('username', 'admin_khusus')->first();
    expect($freshUser->hasRole('admin'))->toBeTrue();
    expect($freshUser->hasRole('Pelaksana'))->toBeFalse();
    expect($freshUser->hasRole('Petugas'))->toBeFalse();
});

test('migration migrates SSO pegawai with Petugas role to Pelaksana safely', function () {
    $petugasRole = Role::where('name', 'Petugas')->first();
    $pelaksanaRole = Role::where('name', 'Pelaksana')->first();

    // 1. Pegawai SSO yang sebelumnya masuk Petugas -> harus dimigrasi ke Pelaksana
    $pegawaiSso = User::create([
        'username'    => 'pegawai_lama_sso',
        'name'        => 'Pegawai Lama SSO',
        'email'       => 'pegawai_lama_sso@telkomuniversity.ac.id',
        'password'    => bcrypt('secret'),
        'auth_source' => 'sso',
        'sso_role'    => 'Petugas',
        'status'      => 'active',
    ]);
    $pegawaiSso->assignRole('Petugas');

    // 2. Petugas manual -> TIDAK boleh dimigrasi
    $petugasManual = User::create([
        'username'    => 'petugas_manual_admin',
        'name'        => 'Petugas Manual Admin',
        'email'       => 'petugas_manual@telkomuniversity.ac.id',
        'password'    => bcrypt('secret'),
        'auth_source' => 'manual',
        'sso_role'    => null,
        'status'      => 'active',
    ]);
    $petugasManual->assignRole('Petugas');

    // 3. Mahasiswa yang ditugaskan Petugas -> TIDAK boleh dimigrasi
    $studentUser = User::create([
        'username'    => 'student_petugas',
        'name'        => 'Student Petugas',
        'email'       => 'student_petugas@student.telkomuniversity.ac.id',
        'password'    => bcrypt('secret'),
        'auth_source' => 'sso',
        'sso_role'    => 'Petugas',
        'status'      => 'active',
    ]);
    $studentUser->syncRoles(['mahasiswa', 'Petugas']);

    // Jalankan migrasi
    $migration = require database_path('migrations/2026_11_10_000001_migrate_sso_pegawai_petugas_to_pelaksana.php');
    $migration->up();

    // Cek hasil:
    // 1. Pegawai SSO berubah ke Pelaksana
    $pegawaiSso->refresh();
    expect($pegawaiSso->hasRole('Pelaksana'))->toBeTrue();
    expect($pegawaiSso->hasRole('Petugas'))->toBeFalse();
    expect($pegawaiSso->sso_role)->toBe('Pelaksana');

    // 2. Petugas manual TETAP Petugas
    $petugasManual->refresh();
    expect($petugasManual->hasRole('Petugas'))->toBeTrue();
    expect($petugasManual->hasRole('Pelaksana'))->toBeFalse();

    // 3. Mahasiswa TETAP memiliki Petugas dan mahasiswa
    $studentUser->refresh();
    expect($studentUser->hasRole('Petugas'))->toBeTrue();
    expect($studentUser->hasRole('mahasiswa'))->toBeTrue();
});
