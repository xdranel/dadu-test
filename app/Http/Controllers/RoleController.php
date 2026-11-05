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
            $request->validate([
                'username' => ['required', 'string', 'max:100'],
                'unit_id'  => ['nullable', 'exists:units,id'],
                'position' => ['nullable', 'string', 'max:255'],
            ]);

            $cleanUsername = explode('@', trim($request->username))[0];
            $cleanUsername = strtolower($cleanUsername);

            $user = User::where('username', $cleanUsername)->first();

            if ($user) {
                $this->guardRoleChange($request, $user, $role->name);
            } else {
                $user = User::create([
                    'username'    => $cleanUsername,
                    'name'        => $cleanUsername,
                    'email'       => $cleanUsername . '@telkomuniversity.ac.id',
                    'password'    => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'auth_source' => 'sso',
                    'status'      => 'active',
                ]);
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

        $sidangTaLocalTester = app()->environment('local')
            && strtolower((string) $user->email) === 'krisna@mahasiswa.test';
        if ($user->hasRole('mahasiswa') && $role->name === 'Petugas' && ! $sidangTaLocalTester) {
            return back()->with('error', 'Tidak dapat assign role Petugas ke akun Mahasiswa. Ubah role mahasiswa terlebih dahulu.');
        }
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
