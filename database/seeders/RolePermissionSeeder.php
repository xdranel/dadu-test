<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define modules and their supported actions
        $modules = [
            'student'           => ['create', 'read', 'update', 'delete'],
            'lecturer'          => ['create', 'read', 'update', 'delete'],
            'course'            => ['create', 'read', 'update', 'delete'],
            'room'              => ['create', 'read', 'update', 'delete'],
            'document_category' => ['create', 'read', 'update', 'delete'],
            'document'          => ['create', 'read', 'update', 'delete'],
            'public_document'   => ['read'],
            'jadwal'            => ['create', 'read', 'update', 'delete'],
            'queue_ticket'      => ['read', 'update'],
            'graduation_registration' => ['create', 'read', 'update', 'delete', 'review'],
            'sidang_ta_registration' => ['create', 'read', 'update', 'review'],
            'sidang_ta_period' => ['create', 'read', 'update', 'delete'],
            'grade_appeal'      => ['create', 'read', 'update', 'delete', 'review'],
        ];

        $allPermissions = [];

        // Generate all permissions
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissionName = "{$module}_{$action}";
                Permission::firstOrCreate(['name' => $permissionName]);
                $allPermissions[] = $permissionName;
            }
        }

        // ─── Akademik (Superadmin — Role ID 106) ───────────────────────────────
        $akademik = Role::findByName('Akademik');
        \App\Support\PermissionRegistry::ensureExist();
        $itPanelPermissions = \App\Support\PermissionRegistry::sensitiveNames();
        $akademik->syncPermissions(array_values(array_unique(array_merge($allPermissions, $itPanelPermissions))));

        // ─── admin (Administrator Operasional) ─────────────────────────────────
        $adminPermissions = array_values(array_filter($allPermissions, function ($p) {
            return !str_ends_with($p, '_review');
        }));
        $admin = Role::findByName('admin');
        $admin->syncPermissions($adminPermissions);

        // ─── Pelaksana (Staf unit teknis — Role ID 103) ────────────────────────
        $pelaksana = Role::findByName('Pelaksana');
        $pelaksana->syncPermissions($allPermissions);

        // ─── Pemimpin (Kepala unit — Role ID 104) ──────────────────────────────
        $pemimpinPermissions = array_filter($allPermissions, function ($p) {
            return str_ends_with($p, '_read') || str_ends_with($p, '_review');
        });
        $pemimpin = Role::findByName('Pemimpin');
        $pemimpin->syncPermissions(array_values($pemimpinPermissions));

        // ─── Pengamat (Rektorat / monitoring — Role ID 105) ───────────────────
        $pengamatPermissions = array_filter($allPermissions, fn($p) => str_ends_with($p, '_read'));
        $pengamat = Role::findByName('Pengamat');
        $pengamat->syncPermissions($pengamatPermissions);

        // ─── Petugas (Front Desk — Role ID 102) ────────────────────────────────
        $petugasPermissions = [];
        foreach ($allPermissions as $perm) {
            if (str_ends_with($perm, '_read')) {
                $petugasPermissions[] = $perm;
            } elseif (str_starts_with($perm, 'queue_ticket_') || str_starts_with($perm, 'jadwal_')) {
                $petugasPermissions[] = $perm;
            } elseif (str_starts_with($perm, 'graduation_registration_') && !str_ends_with($perm, '_delete')) {
                $petugasPermissions[] = $perm;
            } elseif ($perm === 'sidang_ta_registration_review') {
                $petugasPermissions[] = $perm;
            } elseif (str_starts_with($perm, 'sidang_ta_period_') && in_array($perm, ['sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update'], true)) {
                $petugasPermissions[] = $perm;
            } elseif (str_starts_with($perm, 'grade_appeal_') && str_ends_with($perm, '_review')) {
                $petugasPermissions[] = $perm;
            }
        }
        $petugasPermissions = array_unique($petugasPermissions);
        $petugas = Role::findByName('Petugas');
        $petugas->syncPermissions(array_values($petugasPermissions));

        // ─── Mahasiswa (Pengguna — Role ID 101) ───────────────────────────────
        $mahasiswaPermissions = [];
        foreach ($allPermissions as $perm) {
            if (str_ends_with($perm, '_read')) {
                if (
                    str_starts_with($perm, 'document_') ||
                    $perm === 'document_read' ||
                    str_starts_with($perm, 'queue_ticket') ||
                    str_starts_with($perm, 'sidang_ta_period_')
                ) {
                    if (!str_starts_with($perm, 'public_document')) {
                        continue;
                    }
                }
                $mahasiswaPermissions[] = $perm;
            }
        }
        $mahasiswaPermissions[] = 'graduation_registration_create';
        $mahasiswaPermissions[] = 'graduation_registration_update';
        $mahasiswaPermissions[] = 'sidang_ta_registration_create';
        $mahasiswaPermissions[] = 'sidang_ta_registration_update';
        $mahasiswaPermissions[] = 'grade_appeal_create';
        $mahasiswaPermissions[] = 'grade_appeal_update';

        $mahasiswaPermissions = array_unique($mahasiswaPermissions);
        $mahasiswa = Role::findByName('mahasiswa');
        $mahasiswa->syncPermissions($mahasiswaPermissions);

        $this->command->info('RolePermissionSeeder: Permissions berhasil di-assign ke semua role SSC!');
        $this->command->info('  101 mahasiswa → ' . count($mahasiswaPermissions) . ' permissions');
        $this->command->info('  102 Petugas   → ' . count($petugasPermissions) . ' permissions');
        $this->command->info('  103 Pelaksana → Full CRUD + review');
        $this->command->info('  104 Pemimpin  → Read + review');
        $this->command->info('  105 Pengamat  → Read-only');
        $this->command->info('  106 Akademik  → Full access (bypass)');
    }
}
