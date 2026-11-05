<?php

namespace Database\Seeders;

use App\Models\ModuleRolePermission;
use Illuminate\Database\Seeder;

class GraduationRegistrationPermissionSeeder extends Seeder
{
    /**
     * Seed default permissions untuk modul Graduation Registration.
     * 
     * Default matrix sesuai requirement:
     * - Mahasiswa: create ✅, read ✅ (own), update ✅ (own, status submitted/needs_review), delete ❌
     * - Dosen: semua ❌
     * - Staf SSC: create ✅, read ✅, update ✅, delete ❌
     * - Akademik: semua ✅
     * - Direktorat: read ✅ (all), sisanya ❌
     * - Kaprodi: read ✅ (all), sisanya ❌
     */
    public function run(): void
    {
        $moduleName = 'graduation_registration';

        $defaultPermissions = [
            'mahasiswa' => [
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => false,
                'additional_rules' => [
                    'read_scope' => 'own', // Hanya data milik sendiri
                    'update_scope' => 'own', // Hanya data milik sendiri
                    'update_status_constraint' => ['submitted', 'needs_review'], // Hanya bisa update jika status tertentu
                ],
            ],
            'dosen' => [
                'can_create' => false,
                'can_read' => false,
                'can_update' => false,
                'can_delete' => false,
                'additional_rules' => null,
            ],
            'Staf SSC' => [
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => false,
                'additional_rules' => [
                    'read_scope' => 'all',
                    'update_scope' => 'all',
                ],
            ],
            'Akademik' => [
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => true,
                'additional_rules' => [
                    'read_scope' => 'all',
                    'update_scope' => 'all',
                    'delete_scope' => 'all',
                ],
            ],
            'Direktorat' => [
                'can_create' => false,
                'can_read' => true,
                'can_update' => false,
                'can_delete' => false,
                'additional_rules' => [
                    'read_scope' => 'all',
                ],
            ],
            'Kaprodi' => [
                'can_create' => false,
                'can_read' => true,
                'can_update' => false,
                'can_delete' => false,
                'additional_rules' => [
                    'read_scope' => 'all',
                ],
            ],
        ];

        foreach ($defaultPermissions as $role => $permissions) {
            ModuleRolePermission::updateOrCreate(
                [
                    'module_name' => $moduleName,
                    'role_name' => $role,
                ],
                [
                    'can_create' => $permissions['can_create'],
                    'can_read' => $permissions['can_read'],
                    'can_update' => $permissions['can_update'],
                    'can_delete' => $permissions['can_delete'],
                    'additional_rules' => $permissions['additional_rules'],
                ]
            );
        }

        $this->command->info('✓ Default permissions untuk Graduation Registration berhasil di-seed.');
    }
}
