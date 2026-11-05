<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Mapping Role ID SSC → Nama Role Spatie (dadu):
     *   101 → mahasiswa  (SSO auto-assign: pengguna / pengaju layanan)
     *   102 → Petugas    (SSO auto-assign: pegawai / front desk)
     *   103 → Pelaksana  (Manual: staf unit kerja teknis)
     *   104 → Pemimpin   (Manual: kepala unit / pimpinan struktural)
     *   105 → Pengamat   (Manual: rektorat / monitoring read-only)
     *   106 → Akademik   (Manual: superadmin / full access)
     *   ---  → admin     (Manual: administrator operasional)
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Role SSO — auto-assigned saat login pertama kali
        Role::firstOrCreate(['name' => 'mahasiswa']); // Role ID 101: Pengguna / Mahasiswa
        Role::firstOrCreate(['name' => 'Petugas']);   // Role ID 102: Petugas Front Desk / Pegawai

        // Role Manual — di-assign oleh Superadmin melalui panel
        Role::firstOrCreate(['name' => 'Pelaksana']); // Role ID 103: Staf unit kerja teknis
        Role::firstOrCreate(['name' => 'Pemimpin']);   // Role ID 104: Kepala unit / pimpinan struktural
        Role::firstOrCreate(['name' => 'Pengamat']);   // Role ID 105: Rektorat / monitoring read-only
        Role::firstOrCreate(['name' => 'Akademik']);   // Role ID 106: Superadmin (bypass semua hak akses)
        Role::firstOrCreate(['name' => 'admin']);      // Administrator operasional (di bawah Akademik)

        $this->command->info('Roles berhasil dibuat: mahasiswa (101), Petugas (102), Pelaksana (103), Pemimpin (104), Pengamat (105), Akademik (106), admin');
    }
}
