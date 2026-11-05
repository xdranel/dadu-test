<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    /**
     * Tampilkan form penugasan role secara manual (Pre-Assignment).
     */
    public function create()
    {
        $roles = Role::all();
        $seederCodes = array_column(\Database\Seeders\UnitSeeder::UNITS, 'code');
        $units = Unit::whereIn('code', $seederCodes)->orderBy('name')->get();

        if ($units->isEmpty()) {
            (new \Database\Seeders\UnitSeeder())->run();
            $units = Unit::whereIn('code', $seederCodes)->orderBy('name')->get();
        }
        $roleMetadata = array_map(fn($m) => [
            'id'    => $m['id'],
            'label' => $m['label'],
        ], config('roles', []));

        return view('role.assign', compact('roles', 'units', 'roleMetadata'));
    }

    /**
     * Simpan penugasan role (Pre-Assignment) untuk user pegawai/mahasiswa.
     */
    public function store(Request $request)
    {
        $request->validate([
            'username'   => ['required', 'string', 'max:100'],
            'role_id'    => ['nullable'],
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['nullable'],
            'unit_id'    => ['nullable', 'exists:units,id'],
            'position'   => ['nullable', 'string', 'max:255'],
        ]);

        $inputRoles = [];
        if ($request->filled('role_ids')) {
            $inputRoles = (array) $request->input('role_ids', []);
        } elseif ($request->filled('role_id')) {
            $inputRoles = [$request->input('role_id')];
        }

        if (empty($inputRoles)) {
            return back()->with('error', 'Pilih minimal satu role untuk pengguna.');
        }

        $sscMap = [
            '101' => 'mahasiswa',
            '102' => 'Petugas',
            '103' => 'Pelaksana',
            '104' => 'Pemimpin',
            '105' => 'Pengamat',
            '106' => 'Akademik',
        ];

        $resolvedRoles = [];
        foreach ($inputRoles as $rId) {
            $r = null;
            if (is_numeric($rId)) {
                $roleName = $sscMap[(string) $rId] ?? null;
                if ($roleName) {
                    $r = Role::where('name', $roleName)->first();
                }
                if (!$r) {
                    $r = Role::find($rId);
                }
            } else {
                $r = Role::where('name', $rId)->first();
            }

            if ($r && !in_array($r->name, $resolvedRoles, true)) {
                $resolvedRoles[] = $r->name;
            }
        }

        if (empty($resolvedRoles)) {
            return back()->with('error', 'Role yang dipilih tidak valid.');
        }

        $isUnitRequired = in_array('Pelaksana', $resolvedRoles, true) || in_array('Pemimpin', $resolvedRoles, true);

        if ($isUnitRequired) {
            $request->validate([
                'unit_id'  => ['required', 'exists:units,id'],
                'position' => ['required', 'string', 'max:255'],
            ], [
                'unit_id.required'  => 'Unit kerja wajib dipilih untuk role penugasan di unit kerja (Pelaksana/Pemimpin).',
                'position.required' => 'Jabatan / posisi wajib diisi untuk penugasan di unit kerja.',
            ]);
        }

        $cleanUsername = explode('@', trim($request->username))[0];
        $cleanUsername = strtolower($cleanUsername);

        $actor = $request->user();
        if (! $actor->hasRole('Akademik')) {
            $existing = User::where('username', $cleanUsername)->first();

            if ($existing && $existing->id === $actor->id) {
                abort(403, 'Anda tidak dapat mengubah role akun Anda sendiri.');
            }

            $currentRoles = $existing ? $existing->assignedRoleNames() : [];
            if (in_array('Akademik', $currentRoles, true)) {
                abort(403, 'Anda tidak berwenang mengubah role akun superadmin.');
            }

            foreach (array_unique(array_merge($resolvedRoles, $currentRoles)) as $roleName) {
                if ($roleName !== 'mahasiswa' && ! $actor->canGrantRole($roleName)) {
                    abort(403, "Anda tidak berwenang mengelola role {$roleName} (melebihi hak akses Anda).");
                }
            }
        }

        $user = User::firstOrCreate(
            ['username' => $cleanUsername],
            [
                'name'        => $cleanUsername,
                'email'       => $cleanUsername . '@telkomuniversity.ac.id',
                'password'    => Hash::make(Str::random(16)),
                'auth_source' => 'sso',
                'status'      => 'active',
            ]
        );

        if ($isUnitRequired) {
            $user->unit_id  = $request->unit_id;
            $user->position = $request->position;
        } else {
            $user->unit_id  = $request->unit_id ?: null;
            $user->position = $request->position ?: null;
        }

        $isStudent = str_contains($user->email ?? '', '@student.')
            || $user->hasRole('mahasiswa')
            || \App\Models\Student::where('student_number', $cleanUsername)->exists();

        if ($isStudent && !in_array('mahasiswa', $resolvedRoles, true)) {
            $resolvedRoles[] = 'mahasiswa';
        }

        $user->syncRoles($resolvedRoles);

        $priorities = $user->assignedRoleNames();
        $user->sso_role = $priorities[0] ?? $resolvedRoles[0];
        $user->save();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roleLabels = implode(', ', $resolvedRoles);
        return redirect()->back()->with('success', "Role [{$roleLabels}] untuk user '{$cleanUsername}' berhasil disimpan.");
    }
}
