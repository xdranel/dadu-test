<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Selaraskan nama role dadu dengan konsep SSC:
     *
     * Mapping SSC → dadu (nama Spatie):
     *   101 Pengguna  → mahasiswa   (SSO auto-assign, tidak berubah)
     *   102 Petugas   → dosen → RENAME ke 'Petugas'  (pegawai SSO)
     *   103 Pelaksana → Staf SSC → RENAME ke 'Pelaksana'
     *   104 Pemimpin  → Kaprodi → RENAME ke 'Pemimpin'
     *   105 Pengamat  → Direktorat → RENAME ke 'Pengamat'
     *   106 Pengurus  → Akademik  (Superadmin, tidak berubah)
     *
     * Role 'admin' (administrator operasional) tetap dipertahankan.
     */
    public function up(): void
    {
        // Rename: dosen → Petugas (pegawai SSO, front desk)
        DB::table('roles')->where('name', 'dosen')->update(['name' => 'Petugas']);

        // Rename: Staf SSC → Pelaksana
        DB::table('roles')->where('name', 'Staf SSC')->update(['name' => 'Pelaksana']);

        // Rename: Kaprodi → Pemimpin
        DB::table('roles')->where('name', 'Kaprodi')->update(['name' => 'Pemimpin']);

        // Rename: Direktorat → Pengamat
        DB::table('roles')->where('name', 'Direktorat')->update(['name' => 'Pengamat']);

        // Update module_role_permissions jika tabel ada
        if (DB::getSchemaBuilder()->hasTable('module_role_permissions')) {
            DB::table('module_role_permissions')->where('role_name', 'dosen')->update(['role_name' => 'Petugas']);
            DB::table('module_role_permissions')->where('role_name', 'Staf SSC')->update(['role_name' => 'Pelaksana']);
            DB::table('module_role_permissions')->where('role_name', 'Kaprodi')->update(['role_name' => 'Pemimpin']);
            DB::table('module_role_permissions')->where('role_name', 'Direktorat')->update(['role_name' => 'Pengamat']);
        }

        // Update sso_role pada users yang sebelumnya 'pegawai' (SSO auto-assign lama)
        DB::table('users')->where('sso_role', 'pegawai')->update(['sso_role' => 'Petugas']);

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'Petugas')->update(['name' => 'dosen']);
        DB::table('roles')->where('name', 'Pelaksana')->update(['name' => 'Staf SSC']);
        DB::table('roles')->where('name', 'Pemimpin')->update(['name' => 'Kaprodi']);
        DB::table('roles')->where('name', 'Pengamat')->update(['name' => 'Direktorat']);

        if (DB::getSchemaBuilder()->hasTable('module_role_permissions')) {
            DB::table('module_role_permissions')->where('role_name', 'Petugas')->update(['role_name' => 'dosen']);
            DB::table('module_role_permissions')->where('role_name', 'Pelaksana')->update(['role_name' => 'Staf SSC']);
            DB::table('module_role_permissions')->where('role_name', 'Pemimpin')->update(['role_name' => 'Kaprodi']);
            DB::table('module_role_permissions')->where('role_name', 'Pengamat')->update(['role_name' => 'Direktorat']);
        }

        DB::table('users')->where('sso_role', 'Petugas')->update(['sso_role' => 'pegawai']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
