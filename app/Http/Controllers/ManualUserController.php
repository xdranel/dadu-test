<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ManualUserController extends Controller
{
    /**
     * Daftar role yang bisa di-assign ke akun manual via panel ini.
     */
    private function assignableRoles(): array
    {
        // Role yang bisa di-assign via panel manual (selaras SSC)
        // 103 → Pelaksana, 104 → Pemimpin, 105 → Pengamat, 106 → admin/Akademik
        $roles = ['admin', 'Pelaksana', 'Pemimpin', 'Pengamat'];

        // Non-superadmin hanya boleh memberi role dalam kewenangannya sendiri
        $actor = auth()->user();
        if ($actor && ! $actor->hasRole('Akademik')) {
            $roles = array_values(array_filter($roles, fn ($r) => $actor->canGrantRole($r)));
        }

        return $roles;
    }

    /**
     * Non-superadmin tidak boleh menyentuh akun superadmin (Akademik).
     */
    private function guardTarget(User $target): void
    {
        $actor = auth()->user();
        if (! $actor->hasRole('Akademik') && in_array('Akademik', $target->assignedRoleNames(), true)) {
            abort(403, 'Anda tidak berwenang mengelola akun superadmin.');
        }
    }

    /**
     * Tampilkan daftar semua user manual.
     */
    public function index(Request $request)
    {
        $query = User::where('auth_source', 'manual')
            ->with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('superadmin.manual-users.index', compact('users'));
    }

    /**
     * Form tambah user manual baru.
     */
    public function create()
    {
        $roles = $this->assignableRoles();
        return view('superadmin.manual-users.create', compact('roles'));
    }

    /**
     * Simpan user manual baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($this->assignableRoles())],
        ]);

        $user = User::create([
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'username'            => $validated['username'] ?? null,
            'password'            => Hash::make($validated['password']),
            'status'              => 'active',
            'auth_source'         => 'manual',
            'must_change_password' => true, // Paksa ganti password saat login pertama
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('manual-users.index')
            ->with('success', "Akun {$validated['name']} berhasil dibuat dengan role {$validated['role']}. User akan diminta mengganti password saat login pertama kali.");
    }

    /**
     * Form edit user manual.
     */
    public function edit(User $manual_user)
    {
        $this->guardTarget($manual_user);

        if ($manual_user->auth_source !== 'manual') {
            return redirect()->route('manual-users.index')
                ->with('error', 'Hanya akun manual yang dapat diedit dari halaman ini.');
        }

        $roles = $this->assignableRoles();
        $currentRole = $manual_user->getRoleNames()->first();

        return view('superadmin.manual-users.edit', compact('manual_user', 'roles', 'currentRole'));
    }

    /**
     * Update user manual.
     */
    public function update(Request $request, User $manual_user)
    {
        $this->guardTarget($manual_user);

        if ($manual_user->auth_source !== 'manual') {
            return redirect()->route('manual-users.index')
                ->with('error', 'Hanya akun manual yang dapat diedit dari halaman ini.');
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($manual_user->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore($manual_user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($this->assignableRoles())],
            'status'   => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $manual_user->update([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'username' => $validated['username'] ?? $manual_user->username,
            'status'   => $validated['status'],
        ]);

        // Update password jika diisi
        if (!empty($validated['password'])) {
            $manual_user->update(['password' => Hash::make($validated['password'])]);
        }

        // Sync role (non-superadmin tidak boleh mengubah role akunnya sendiri)
        if ($manual_user->id === auth()->id() && ! auth()->user()->hasRole('Akademik')
            && ! in_array($validated['role'], $manual_user->assignedRoleNames(), true)) {
            abort(403, 'Anda tidak dapat mengubah role akun Anda sendiri.');
        }
        $manual_user->syncRoles([$validated['role']]);

        return redirect()->route('manual-users.index')
            ->with('success', "Akun {$validated['name']} berhasil diperbarui.");
    }

    /**
     * Nonaktifkan user manual (soft-disable, bukan hard delete).
     */
    public function destroy(User $manual_user)
    {
        $this->guardTarget($manual_user);

        if ($manual_user->auth_source !== 'manual') {
            return redirect()->route('manual-users.index')
                ->with('error', 'Hanya akun manual yang dapat dinonaktifkan dari halaman ini.');
        }

        // Jangan izinkan nonaktifkan diri sendiri
        if ($manual_user->id === auth()->id()) {
            return redirect()->route('manual-users.index')
                ->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $manual_user->update(['status' => 'inactive']);

        return redirect()->route('manual-users.index')
            ->with('success', "Akun {$manual_user->name} berhasil dinonaktifkan.");
    }

    /**
     * Reset password akun manual oleh admin (untuk kasus lupa password).
     * Password baru akan di-generate secara acak dan ditampilkan SEKALI ke admin.
     */
    public function resetPassword(User $manual_user)
    {
        $this->guardTarget($manual_user);

        if ($manual_user->auth_source !== 'manual') {
            return back()->with('error', 'Hanya akun manual yang dapat direset passwordnya.');
        }

        // Jangan izinkan reset password diri sendiri (gunakan fitur ganti password)
        if ($manual_user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat mereset password akun Anda sendiri. Gunakan fitur Ganti Password di menu Profil.');
        }

        // Generate password acak yang aman (12 karakter: huruf + angka + simbol)
        $newPassword = Str::password(12, letters: true, numbers: true, symbols: true, spaces: false);

        // Update password & set flag must_change_password
        $manual_user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        // Invalidate semua sesi user tersebut (logout paksa dari semua device)
        DB::table('sessions')->where('user_id', $manual_user->id)->delete();

        // Log activity (TIDAK menyimpan password plaintext)
        activity()
            ->causedBy(Auth::user())
            ->performedOn($manual_user)
            ->withProperties(['action' => 'admin_reset_password'])
            ->log("Admin mereset password user: {$manual_user->name}");

        // Flash password ke session SEKALI PAKAI (akan otomatis hilang setelah ditampilkan)
        return back()->with([
            'generated_password' => $newPassword,
            'reset_user_name' => $manual_user->name,
        ]);
    }
}
