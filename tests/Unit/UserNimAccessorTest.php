<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'mahasiswa']);
    Role::firstOrCreate(['name' => 'Pelaksana']);
    Role::firstOrCreate(['name' => 'Petugas']);
    Role::firstOrCreate(['name' => 'admin']);
});

test('user dengan role mahasiswa mengembalikan employee_id sebagai nim dengan leading zeros tetap utuh', function () {
    $mhs = User::factory()->create([
        'employee_id' => '01234567',
    ]);
    $mhs->assignRole('mahasiswa');

    expect($mhs->nim)->toBe('01234567')
        ->toBeString();
});

test('user non-mahasiswa mengembalikan null untuk nim meskipun memiliki employee_id (NIP/NIK)', function () {
    $pelaksana = User::factory()->create([
        'employee_id' => '198701012010121001',
    ]);
    $pelaksana->assignRole('Pelaksana');

    $petugas = User::factory()->create([
        'employee_id' => '0987654321',
    ]);
    $petugas->assignRole('Petugas');

    $admin = User::factory()->create([
        'employee_id' => '00112233',
    ]);
    $admin->assignRole('admin');

    expect($pelaksana->nim)->toBeNull();
    expect($petugas->nim)->toBeNull();
    expect($admin->nim)->toBeNull();
});

test('user mahasiswa tanpa employee_id mengembalikan null', function () {
    $mhs = User::factory()->create([
        'employee_id' => null,
    ]);
    $mhs->assignRole('mahasiswa');

    expect($mhs->nim)->toBeNull();
});
