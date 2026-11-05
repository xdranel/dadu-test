<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModuleRolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModulePermissionController extends Controller
{
    /**
     * Show the module permission configuration page.
     */
    public function index()
    {
        // Daftar module yang bisa dikonfigurasi
        $modules = [
            'graduation_registration' => 'Pendaftaran Yudisium',
        ];

        // Daftar role yang ada
        $roles = ['mahasiswa', 'dosen', 'admin', 'Staf SSC', 'Direktorat', 'Kaprodi'];

        // Ambil semua permission yang sudah ada
        $permissions = ModuleRolePermission::all()->groupBy('module_name');

        return view('admin.module-permissions.index', compact('modules', 'roles', 'permissions'));
    }

    /**
     * Update permissions untuk module tertentu.
     */
    public function update(Request $request, string $moduleName)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.role' => 'required|string',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_read' => 'boolean',
            'permissions.*.can_update' => 'boolean',
            'permissions.*.can_delete' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $permissionsData = $request->input('permissions', []);

        foreach ($permissionsData as $permData) {
            $roleName = $permData['role'];
            
            ModuleRolePermission::updateOrCreate(
                [
                    'module_name' => $moduleName,
                    'role_name' => $roleName,
                ],
                [
                    'can_create' => $permData['can_create'] ?? false,
                    'can_read' => $permData['can_read'] ?? false,
                    'can_update' => $permData['can_update'] ?? false,
                    'can_delete' => $permData['can_delete'] ?? false,
                ]
            );
        }

        return redirect()->back()
            ->with('success', 'Permission berhasil diperbarui untuk module ' . $moduleName);
    }

    /**
     * Reset permissions to default for a module.
     */
    public function reset(string $moduleName)
    {
        if ($moduleName === 'graduation_registration') {
            // Re-run seeder for this module
            \Artisan::call('db:seed', [
                '--class' => 'GraduationRegistrationPermissionSeeder',
                '--force' => true,
            ]);

            return redirect()->back()
                ->with('success', 'Permission berhasil direset ke default untuk module ' . $moduleName);
        }

        return redirect()->back()
            ->with('error', 'Module tidak ditemukan');
    }
}
