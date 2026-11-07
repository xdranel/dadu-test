<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SidangTaFormGuidePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['create', 'read', 'update', 'delete'] as $action) {
            $permission = Permission::firstOrCreate(['name' => 'sidang_ta_form_guide_'.$action, 'guard_name' => 'web']);
            Role::where('name', 'Akademik')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
