<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create new permissions for the graduation_registration module
        $permissions = [
            'graduation_registration_create',
            'graduation_registration_read',
            'graduation_registration_update',
            'graduation_registration_delete',
            'graduation_registration_review',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to admin — all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        // Assign to pegawai — all permissions
        $pegawai = Role::firstOrCreate(['name' => 'pegawai', 'guard_name' => 'web']);
        $pegawai->givePermissionTo($permissions);

        // Assign to mahasiswa — create, read, update own records
        $mahasiswa = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $mahasiswa->givePermissionTo([
            'graduation_registration_create',
            'graduation_registration_read',
            'graduation_registration_update',
        ]);

        // Assign to dosen — read only
        $dosen = Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $dosen->givePermissionTo([
            'graduation_registration_read',
        ]);
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'graduation_registration_create',
            'graduation_registration_read',
            'graduation_registration_update',
            'graduation_registration_delete',
            'graduation_registration_review',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::findByName($permName, 'web');
            if ($perm) {
                $perm->delete();
            }
        }
    }
};
