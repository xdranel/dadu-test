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

        $role = Role::where('name', 'mahasiswa')->first();
        if ($role) {
            $role->revokePermissionTo([
                'document_read',
                'document_category_read'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'mahasiswa')->first();
        if ($role) {
            $role->givePermissionTo([
                'document_read',
                'document_category_read'
            ]);
        }
    }
};
