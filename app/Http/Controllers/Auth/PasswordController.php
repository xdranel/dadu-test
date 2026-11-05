<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        if (Auth::user()->isSsoAccount()) {
            return back()->withErrors([
                'current_password' => 'Akun SSO tidak dapat mengganti password melalui sistem ini. Silakan hubungi Hotline Akademik untuk reset password Anda.',
            ])->withInput();
        }

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Password baru tidak boleh sama dengan password lama.',
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Invalidate sesi lain di device lain
        Auth::logoutOtherDevices($validated['password']);

        // Log activity
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['action' => 'self_change_password'])
            ->log('User mengganti password sendiri');

        return back()->with('status', 'password-updated');
    }

    /**
     * Halaman paksa ganti password (untuk akun baru atau password yang direset admin).
     */
    public function forceChange(): View
    {
        // Akun SSO tidak boleh mengakses form ganti password
        if (Auth::user()->isSsoAccount()) {
            abort(403, 'Akun SSO tidak dapat mengganti password melalui sistem ini. Silakan hubungi Hotline Akademik untuk reset password Anda.');
        }

        return view('auth.force-change-password');
    }

    /**
     * Proses ganti password wajib.
     */
    public function forceChangeUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Password baru tidak boleh sama dengan password lama.',
        ]);

        $user = $request->user();

        // Update password dan reset flag
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Invalidate sesi lain di device lain
        Auth::logoutOtherDevices($validated['password']);

        // Log activity
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['action' => 'forced_password_change'])
            ->log('User mengganti password wajib (setelah reset admin atau akun baru)');

        return redirect()->route('dashboard')
            ->with('success', 'Password berhasil diperbarui. Anda sekarang dapat mengakses sistem.');
    }
}
