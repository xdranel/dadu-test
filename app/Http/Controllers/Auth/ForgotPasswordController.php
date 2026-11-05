<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showStep1(): View
    {
        return view('auth.forgot-password-step1');
    }

    /**
     * Submit username, simpan ke session, redirect ke step 2.
     */
    public function submitStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
        ]);

        $username = $validated['username'];
        $ip = $request->ip();

        //Audit log
        activity()
            ->withProperties([
                'action' => 'forgot_password_step1_attempt',
                'username_attempted' => $username,
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ])
            ->log("Forgot password step 1: username '{$username}' dari IP {$ip}");

        // Simpan username ke session untuk digunakan di step 2
        session(['forgot_password_username' => $username]);

        //Anti-enumeration
        return redirect()->route('forgot-password.step2');
    }

    /**
     * STEP 2: Tampilkan form input password baru.
     */
    public function showStep2(): View
    {
        // Jika tidak ada username di session, redirect kembali ke step 1
        if (!session()->has('forgot_password_username')) {
            return redirect()->route('forgot-password.step1')
                ->with('error', 'Sesi telah berakhir. Silakan mulai dari awal.');
        }

        return view('auth.forgot-password-step2');
    }

    /**
     * STEP 2: Submit password baru, validasi lengkap, update password.
     */
    public function submitStep2(Request $request): RedirectResponse
    {
        // Cek session username
        $username = session('forgot_password_username');
        if (!$username) {
            return redirect()->route('forgot-password.step1')
                ->with('error', 'Sesi telah berakhir. Silakan mulai dari awal.');
        }

        // Validasi password input
        $validated = $request->validate([
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $ip = $request->ip();

        // Cari user berdasarkan username
        $user = User::where('username', $username)->first();

        // VALIDASI LENGKAP - MITIGASI #3, #4, #5
        // Jika salah satu kondisi tidak terpenuhi, tampilkan pesan generik yang SAMA
        $isValid = $user 
            && $user->auth_source === 'manual'  // Hanya akun manual
            && $user->status === 'active'       // Hanya akun aktif
            && !$user->isSuperAdmin();          // EXCLUDE role Akademik (Superadmin)

        if (!$isValid) {
            // Audit log
            activity()
                ->withProperties([
                    'action' => 'forgot_password_failed',
                    'username_attempted' => $username,
                    'ip_address' => $ip,
                    'reason' => $user ? 'invalid_conditions' : 'user_not_found',
                    'user_agent' => $request->userAgent(),
                ])
                ->log("Forgot password gagal: username '{$username}' dari IP {$ip}");

            // Clear session
            session()->forget('forgot_password_username');

            // Pesan generik - tidak membocorkan detail alasan
            return redirect()->route('login')
                ->with('error', 'Permintaan tidak dapat diproses. Silakan hubungi administrator untuk bantuan reset password.');
        }

        // Update password & set flag
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Invalidate semua sesi lama (paksa logout dari semua device)
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Audit log - catat keberhasilan
        activity()
            ->performedOn($user)
            ->withProperties([
                'action' => 'forgot_password_success',
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ])
            ->log("Forgot password berhasil: user '{$user->name}' (ID: {$user->id}) dari IP {$ip}");

        // Clear session
        session()->forget('forgot_password_username');

        // Redirect ke login dengan pesan sukses
        return redirect()->route('login')
            ->with('success', 'Password berhasil direset. Silakan login dengan password baru Anda.');
    }
}
