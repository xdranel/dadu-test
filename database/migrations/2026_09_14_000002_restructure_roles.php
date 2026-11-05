<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Restrukturisasi role:
     * 1. Rename role 'admin' → 'Akademik'
     * 2. Hapus role 'pegawai' beserta relasi di model_has_roles & role_has_permissions
     * 3. Buat role baru: 'Staf SSC', 'Direktorat', 'Kaprodi'
     * 4. Backfill kolom auth_source berdasarkan sso_id
     */
    public function up(): void
    {
        // 1. Rename role 'admin' → 'Akademik'
        DB::table('roles')->where('name', 'admin')->update(['name' => 'Akademik']);

        // 2. Hapus role 'pegawai' dan semua relasinya
        $pegawaiRole = DB::table('roles')->where('name', 'pegawai')->first();
        if ($pegawaiRole) {
            DB::table('model_has_roles')->where('role_id', $pegawaiRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $pegawaiRole->id)->delete();
            DB::table('roles')->where('id', $pegawaiRole->id)->delete();
        }

        // 3. Buat role baru (guard_name = 'web' sesuai default Spatie)
        $now = now();
        foreach (['Staf SSC', 'Direktorat', 'Kaprodi'] as $roleName) {
            DB::table('roles')->insertOrIgnore([
                'name'       => $roleName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. Backfill auth_source
        DB::table('users')->whereNotNull('sso_id')->where('sso_id', '!=', '')->update(['auth_source' => 'sso']);
        DB::table('users')->where(function ($query) {
            $query->whereNull('sso_id')->orWhere('sso_id', '');
        })->update(['auth_source' => 'manual']);

        // 5. Update module_role_permissions: rename 'pegawai' entries
        if (DB::getSchemaBuilder()->hasTable('module_role_permissions')) {
            DB::table('module_role_permissions')->where('role_name', 'pegawai')->update(['role_name' => 'Staf SSC']);
            DB::table('module_role_permissions')->where('role_name', 'super_admin')->update(['role_name' => 'Akademik']);
        }

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename 'Akademik' back to 'admin'
        DB::table('roles')->where('name', 'Akademik')->update(['name' => 'admin']);

        // Recreate 'pegawai' role
        $now = now();
        DB::table('roles')->insertOrIgnore([
            'name'       => 'pegawai',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Remove new roles
        foreach (['Staf SSC', 'Direktorat', 'Kaprodi'] as $roleName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if ($role) {
                DB::table('model_has_roles')->where('role_id', $role->id)->delete();
                DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
                DB::table('roles')->where('id', $role->id)->delete();
            }
        }

        // Revert module_role_permissions
        if (DB::getSchemaBuilder()->hasTable('module_role_permissions')) {
            DB::table('module_role_permissions')->where('role_name', 'Staf SSC')->update(['role_name' => 'pegawai']);
            DB::table('module_role_permissions')->where('role_name', 'Akademik')->update(['role_name' => 'super_admin']);
        }

        // Reset auth_source
        DB::table('users')->update(['auth_source' => null]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
