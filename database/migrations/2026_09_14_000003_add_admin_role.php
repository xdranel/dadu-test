<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan role 'admin' sebagai role manual di bawah Superadmin (Akademik).
     */
    public function up(): void
    {
        $now = now();
        DB::table('roles')->insertOrIgnore([
            'name'       => 'admin',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Clear Spatie cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = DB::table('roles')->where('name', 'admin')->first();
        if ($role) {
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
