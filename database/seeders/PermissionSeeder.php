<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view student',
            'view lecturer',
            'view course',
            'view room',
            'view document category',
            'view document',
            'view public document',
            'manage role settings',
            'view exam schedules',
            'manage exam schedules',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->syncPermissions($permissions);
        }

        $petugas = Role::where('name', 'Petugas')->first() ?? Role::where('name', 'dosen')->first();
        if ($petugas) {
            $petugas->syncPermissions([
                'view course',
                'view room',
                'view public document',
                'view exam schedules',
                'manage exam schedules',
            ]);
        }

        $mahasiswa = Role::where('name', 'mahasiswa')->first();
        if ($mahasiswa) {
            $mahasiswa->syncPermissions([
                'view course',
                'view public document',
                'view exam schedules',
            ]);
        }

        $pelaksana = Role::where('name', 'Pelaksana')->first() ?? Role::where('name', 'Staf SSC')->first();
        if ($pelaksana) {
            $pelaksana->syncPermissions($permissions);
        }

        $this->command->info('Permissions berhasil dibuat dan di-assign ke role.');
    }
}