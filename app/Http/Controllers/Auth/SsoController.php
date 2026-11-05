<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    /**
     * Handle authentication process.
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginApi(LoginRequest $request)
    {
        // Jalankan proses autentikasi (hybrid SSO & Lokal)
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Log login berhasil
        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'username' => $user->username ?? $user->email,
            'auth_source' => $user->auth_source,
            'role' => $user->getRoleNames()->first() ?? 'none',
            'ip' => $request->ip(),
        ]);

        // Redirect dinamis berdasarkan Role
        return redirect()->intended('/dashboard');
    }

    /**
     * Handle logout process.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        $username = Auth::user()->username ?? Auth::user()->email;

        if ($user = Auth::user()) {
            try {
                app(\App\Services\QueueCounterService::class)->releaseForLogout($user);
            } catch (\Throwable $e) {
                // Jangan gagalkan logout jika ada issue loket
            }
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('User logged out', [
            'user_id' => $userId,
            'username' => $username,
            'ip' => $request->ip(),
        ]);

        return redirect('/login');
    }
}
