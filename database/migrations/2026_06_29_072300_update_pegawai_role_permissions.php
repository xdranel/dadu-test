<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Forget cached permissions first
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'pegawai')->first();
        if ($role) {
            $allPermissions = Permission::all();
            $role->syncPermissions($allPermissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'pegawai')->first();
        if ($role) {
            // Revert back to only read and update permissions
            $allPermissions = Permission::all();
            $readUpdatePermissions = $allPermissions->filter(function ($perm) {
                return str_ends_with($perm->name, '_read') || str_ends_with($perm->name, '_update');
            });
            $role->syncPermissions($readUpdatePermissions);
        }
    }
};
