<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'sidang_ta_registration_create', 'sidang_ta_registration_read',
        'sidang_ta_registration_update', 'sidang_ta_registration_review',
        'sidang_ta_period_create', 'sidang_ta_period_read',
        'sidang_ta_period_update', 'sidang_ta_period_delete',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $groups = [
            'mahasiswa' => ['sidang_ta_registration_create', 'sidang_ta_registration_read', 'sidang_ta_registration_update'],
            'Petugas' => ['sidang_ta_registration_read', 'sidang_ta_registration_review', 'sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update'],
            'Pelaksana' => $this->permissions,
            'Pemimpin' => ['sidang_ta_registration_read', 'sidang_ta_registration_review', 'sidang_ta_period_read'],
            'admin' => ['sidang_ta_registration_create', 'sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update', 'sidang_ta_period_delete'],
            'Akademik' => $this->permissions,
        ];
        foreach ($groups as $roleName => $names) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->givePermissionTo($names);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->permissions as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) {
                foreach (Role::where('guard_name', 'web')->get() as $role) $role->revokePermissionTo($permission);
                $permission->delete();
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
