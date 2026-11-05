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

        // Create new permissions for the grade_appeal module
        $permissions = [
            'grade_appeal_create',
            'grade_appeal_read',
            'grade_appeal_update',
            'grade_appeal_delete',
            'grade_appeal_review',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to admin — all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        // Assign to pegawai — all except delete
        $pegawai = Role::firstOrCreate(['name' => 'pegawai', 'guard_name' => 'web']);
        $pegawai->givePermissionTo([
            'grade_appeal_create',
            'grade_appeal_read',
            'grade_appeal_update',
            'grade_appeal_review',
        ]);

        // Assign to mahasiswa — create and read own records
        $mahasiswa = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $mahasiswa->givePermissionTo([
            'grade_appeal_create',
            'grade_appeal_read',
        ]);

        // Assign to dosen — read only
        $dosen = Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $dosen->givePermissionTo([
            'grade_appeal_read',
        ]);
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'grade_appeal_create',
            'grade_appeal_read',
            'grade_appeal_update',
            'grade_appeal_delete',
            'grade_appeal_review',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->delete();
            }
        }
    }
};
