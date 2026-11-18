<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Tampilkan daftar semua role beserta statistik user.
     */
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->get();
        $metadata = config('roles');

        return view('role.index', compact('roles', 'metadata'));
    }

    private function abortIfSelfEscalation(Request $request, Role $role): void
    {
        $actor = $request->user();

        if (! $actor->hasRole('Akademik') && in_array($role->name, $actor->assignedRoleNames(), true)) {
            abort(403, 'Anda tidak dapat mengubah hak akses role yang sedang Anda miliki.');
        }
    }

    /**
     * Form edit hak akses (permissions) suatu role + daftar user yang memiliki role ini.
     */
    public function edit(Request $request, Role $role)
    {
        if ($role->name === 'Akademik') {
            return redirect()->route('role.index')
                ->with('error', 'Hak akses role Pengurus tidak dapat diubah melalui halaman ini.');
        }

        $this->abortIfSelfEscalation($request, $role);

        PermissionRegistry::ensureExist();

        $modules = PermissionRegistry::modules();
        $actions = PermissionRegistry::standardActions();
        $groups = collect($modules)->groupBy('group', true)->keys()->all();
        $rolePermissions = $role->permissions()->pluck('name')->toArray();
        $manageable = PermissionRegistry::manageableBy($request->user());

        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        $isMahasiswa = ($role->name === 'mahasiswa');

        if ($isMahasiswa && $search === '') {
            $roleUsers = $role->users()->whereRaw('1 = 0')->paginate(10)->withQueryString();
        } else {
            $query = $role->users()->with('unit')->orderBy('name');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $roleUsers = $query->paginate(10)->withQueryString();
        }

        $seederCodes = array_column(\Database\Seeders\UnitSeeder::UNITS, 'code');
        $units = \App\Models\Unit::whereIn('code', $seederCodes)->orderBy('name')->get();

        if ($units->isEmpty()) {
            (new \Database\Seeders\UnitSeeder())->run();
            $units = \App\Models\Unit::whereIn('code', $seederCodes)->orderBy('name')->get();
        }

        $metadata = config('roles');
        $roleMeta = $metadata[$role->name] ?? null;

        return view('role.edit', compact(
            'role', 'modules', 'actions', 'groups', 'rolePermissions', 'manageable',
            'roleUsers', 'units', 'roleMeta', 'search', 'isMahasiswa'
        ));
    }

    /**
     * Sinkronisasikan permissions suatu role.
     */
    public function update(Request $request, Role $role)
    {
        if ($role->name === 'Akademik') {
            return redirect()->route('role.index')
                ->with('error', 'Hak akses role Pengurus (Akademik) tidak dapat diubah melalui halaman ini.');
        }

        $this->abortIfSelfEscalation($request, $role);

        PermissionRegistry::ensureExist();

        $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::allNames())],
        ]);

        $manageable = PermissionRegistry::manageableBy($request->user());
        $requested  = array_values(array_intersect($request->input('permissions', []), $manageable));

        $current  = $role->permissions()->pluck('name')->toArray();
        $retained = array_values(array_diff($current, $manageable));

        $role->syncPermissions(array_values(array_unique(array_merge($retained, $requested))));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('role.index')
            ->with('success', "Hak akses role {$role->name} berhasil diperbarui.");
    }

    /**
     * Pastikan actor berwenang mengubah role $roleName pada $target.
     */
    private function guardRoleChange(Request $request, User $target, string $roleName)
    {
        $actor = $request->user();

        if ($actor->hasRole('Akademik')) {
            return null;
        }

        if ($target->id === $actor->id) {
            abort(403, 'Anda tidak dapat mengubah role akun Anda sendiri.');
        }

        if (in_array('Akademik', $target->assignedRoleNames(), true)) {
            abort(403, 'Anda tidak berwenang mengubah role akun superadmin.');
        }

        if (! $actor->canGrantRole($roleName)) {
            abort(403, 'Anda tidak berwenang mengelola role tersebut (melebihi hak akses Anda).');
        }

        return null;
    }

    /**
     * Assign user ke role
     */
    public function assignUser(Request $request, Role $role)
    {
        $actor = $request->user();
        if (! $actor->hasRole('Akademik') && ! $actor->canGrantRole($role->name)) {
            abort(403, 'Anda tidak berwenang memberikan role tersebut (melebihi hak akses Anda).');
        }

        $user = null;

        if ($request->filled('username')) {
            $rawInput = trim($request->username);

            if (mb_strlen($rawInput) > 100) {
                return back()->with('error', 'Format username atau email tidak valid (maksimal 100 karakter).');
            }

            $isEmail = str_contains($rawInput, '@');

            if ($isEmail) {
                if (! filter_var($rawInput, FILTER_VALIDATE_EMAIL)) {
                    return back()->with('error', 'Format email SSO tidak valid.');
                }

                $domain = strtolower(substr(strrchr($rawInput, '@'), 1));
                $allowedBaseDomains = ['telkomuniversity.ac.id', 'student.telkomuniversity.ac.id'];
                $isAllowedDomain = false;
                foreach ($allowedBaseDomains as $base) {
                    if ($domain === $base || str_ends_with($domain, '.' . $base)) {
                        $isAllowedDomain = true;
                        break;
                    }
                }

                $isTestDomain = str_ends_with($domain, '.test') || str_ends_with($domain, '.local') || $domain === 'example.com';
                if (! $isAllowedDomain && app()->environment('local', 'testing') && $isTestDomain) {
                    $isAllowedDomain = true;
                }

                if (! $isAllowedDomain) {
                    return back()->with('error', 'Domain email tidak valid. Gunakan akun SSO dengan domain @telkomuniversity.ac.id atau @student.telkomuniversity.ac.id.');
                }
            } else {
                if (! preg_match('/^[a-zA-Z0-9._-]+$/', $rawInput)) {
                    return back()->with('error', 'Format username SSO atau NIM tidak valid.');
                }
            }

            $cleanUsername = strtolower(explode('@', $rawInput)[0]);

            $isStudentDomain = $isEmail && (str_contains(strtolower($rawInput), '@student.') || str_starts_with(strtolower($domain ?? ''), 'student.'));
            $studentModel = \App\Models\Student::where('student_number', $cleanUsername)
                ->orWhere('email', strtolower($rawInput))
                ->first();

            $isStudent = $isStudentDomain || ($studentModel !== null) || ($role->name === 'mahasiswa');

            $user = User::where('username', $cleanUsername)
                ->orWhere('email', strtolower($rawInput))
                ->first();

            if ($user && ($user->hasRole('mahasiswa') || str_contains($user->email ?? '', '@student.'))) {
                $isStudent = true;
            }

            // Pengecekan akun mahasiswa saat ditugaskan ke role Petugas
            if ($role->name === 'Petugas' && $isStudent && ! $user && ! $studentModel) {
                return back()->with('error', 'Akun mahasiswa tidak ditemukan di database. Pastikan NIM atau email mahasiswa telah terdaftar.');
            }

            if ($user) {
                $this->guardRoleChange($request, $user, $role->name);
            } else {
                $userName = $studentModel?->name ?? $cleanUsername;
                $userEmail = $isEmail
                    ? strtolower($rawInput)
                    : ($studentModel?->email ?? ($isStudent ? $cleanUsername . '@student.telkomuniversity.ac.id' : $cleanUsername . '@telkomuniversity.ac.id'));

                $user = User::create([
                    'username'    => $cleanUsername,
                    'name'        => $userName,
                    'email'       => $userEmail,
                    'employee_id' => $studentModel?->student_number ?? ($isStudent ? $cleanUsername : null),
                    'password'    => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'auth_source' => 'sso',
                    'status'      => 'active',
                ]);

                if ($isStudent) {
                    $user->assignRole('mahasiswa');
                }
            }

            if ($request->filled('unit_id')) {
                $user->unit_id = $request->unit_id;
            }
            if ($request->filled('position')) {
                $user->position = $request->position;
            }
            $user->save();
        } else {
            $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);

            $user = User::findOrFail($request->user_id);
            $this->guardRoleChange($request, $user, $role->name);
        }

        // Mahasiswa diizinkan memiliki role Petugas sebagai multi-role
        $user->assignRole($role->name);
        $user->update(['sso_role' => $role->name]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "User '{$user->username}' ({$user->name}) berhasil diberi role {$role->name}.");
    }

    /**
     * Nonaktifkan / aktifkan user atau cabut role dari user.
     */
    public function revokeUser(Request $request, Role $role)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'search'  => ['nullable', 'string', 'max:100'],
        ]);

        if ((int) $request->user_id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat mengubah status akun Anda sendiri.');
        }

        $user = User::findOrFail($request->user_id);

        if (! $user->hasRole($role->name)) {
            abort(404, 'Pengguna tidak memiliki role ini.');
        }

        $this->guardRoleChange($request, $user, $role->name);

        if ($user->status === 'inactive') {
            $user->update(['status' => 'active']);
            $message = "Akun '{$user->name}' berhasil diaktifkan kembali.";
        } else {
            $user->update(['status' => 'inactive']);
            try {
                \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
            }
            $message = "Akun '{$user->name}' berhasil dinonaktifkan.";
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $redirectUrl = route('role.edit', $role->id);
        if ($request->filled('search')) {
            $redirectUrl .= '?search=' . urlencode($request->input('search'));
        }
        $redirectUrl .= '#tab-users';

        return redirect()->to($redirectUrl)->with('success', $message);
    }
}
