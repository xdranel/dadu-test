<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Superadmin (Akademik) — akun manual ───
        $user = User::where('email', 'superadmin@example.test')->first();
        if (!$user) {
            $user = User::create([
                'username'    => 'superadmin',
                'name'        => 'Super Admin',
                'email'       => 'superadmin@example.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $user->update(['auth_source' => 'manual']);
        }
        $user->syncRoles(['Akademik']);

        // ─── Admin (Administrator Operasional) — akun manual ───
        $adminUser = User::where('email', 'admin@example.test')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'username'    => 'admin',
                'name'        => 'Admin Operasional',
                'email'       => 'admin@example.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $adminUser->update(['auth_source' => 'manual']);
        }
        $adminUser->syncRoles(['admin']);

        // ─── Mahasiswa Test User — akun SSO ───
        $mhs = User::where('username', '1202200001')
            ->orWhere('email', 'krisna@mahasiswa.test')
            ->first();

        if (!$mhs) {
            $mhs = User::create([
                'username'    => '1202200001',
                'name'        => 'Krisna Mahasiswa',
                'email'       => 'krisna@mahasiswa.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'sso',
            ]);
        } else {
            $mhs->update(['auth_source' => 'sso']);
        }
        $mhs->syncRoles(['mahasiswa']);

        \App\Models\Student::updateOrCreate(
            ['student_number' => '1202200001'],
            [
                'name'           => 'Krisna Mahasiswa',
                'academic_year'  => '2022',
                'study_program'  => 'Informatika',
                'email'          => 'krisna@mahasiswa.test',
                'status'         => 'Aktif',
            ]
        );

        // ─── Petugas Test User — akun SSO ───
        $dsn = User::where('username', 'krisna_dosen')
            ->orWhere('email', 'krisna@dosen.test')
            ->first();
        if (!$dsn) {
            $dsn = User::create([
                'username'    => 'krisna_dosen',
                'name'        => 'Krisna Petugas',
                'email'       => 'krisna@dosen.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'sso',
                'sso_role'    => 'Petugas',
            ]);
        } else {
            $dsn->update(['auth_source' => 'sso', 'sso_role' => 'Petugas']);
        }
        $dsn->syncRoles(['Petugas']);

        // ─── Pelaksana Test User — akun manual ───
        $ssc = User::where('email', 'staf_ssc@example.test')->first();
        if (!$ssc) {
            $ssc = User::create([
                'username'    => 'staf_ssc',
                'name'        => 'User Pelaksana',
                'email'       => 'staf_ssc@example.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $ssc->update(['auth_source' => 'manual']);
        }
        $ssc->syncRoles(['Pelaksana']);

        // ─── Pengamat Test User — akun manual ───
        $dir = User::where('email', 'direktorat@example.test')->first();
        if (!$dir) {
            $dir = User::create([
                'username'    => 'direktorat',
                'name'        => 'User Pengamat',
                'email'       => 'direktorat@example.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $dir->update(['auth_source' => 'manual']);
        }
        $dir->syncRoles(['Pengamat']);

        // ─── Pemimpin Test User — akun manual ───
        $kpd = User::where('email', 'kaprodi@example.test')->first();
        if (!$kpd) {
            $kpd = User::create([
                'username'    => 'kaprodi',
                'name'        => 'User Pemimpin',
                'email'       => 'kaprodi@example.test',
                'password'    => bcrypt('password'),
                'status'      => 'active',
                'auth_source' => 'manual',
            ]);
        } else {
            $kpd->update(['auth_source' => 'manual']);
        }
        $kpd->syncRoles(['Pemimpin']);
    }
}