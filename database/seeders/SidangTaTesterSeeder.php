<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SidangTaTesterSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) return;
        $user = User::where('username', '1202200001')->orWhere('email', 'krisna@mahasiswa.test')->first();
        if (!$user) {
            $user = User::create([
                'username' => '1202200001',
                'name' => 'Krisna Mahasiswa',
                'email' => 'krisna@mahasiswa.test',
                'password' => Hash::make('password'),
                'status' => 'active',
                'auth_source' => 'manual',
            ]);
        }
        $user->update(['email' => 'krisna@mahasiswa.test', 'auth_source' => 'manual', 'status' => 'active']);
        $roles = Role::whereIn('name', ['mahasiswa', 'Petugas'])->pluck('name')->all();
        if (count($roles) < 2) {
            $this->command?->warn('Role mahasiswa/Petugas belum tersedia. Jalankan RoleSeeder terlebih dahulu.');
            return;
        }
        $user->syncRoles($roles);
        Student::updateOrCreate(
            ['student_number' => '1202200001'],
            ['name' => 'Krisna Mahasiswa', 'academic_year' => '2022', 'study_program' => 'Informatika', 'email' => 'krisna@mahasiswa.test', 'status' => 'Aktif']
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Akun Krisna lokal dapat berpindah antara role Mahasiswa dan Petugas.');
    }
}
