<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Hanya berlaku untuk akun manual (bukan SSO)
        if (!$user || !$user->isManualAccount()) {
            return $next($request);
        }

        // Jika must_change_password = true, paksa ke halaman ganti password
        if ($user->must_change_password) {
            // Kecuali sudah di halaman ganti password atau logout
            if (!$request->routeIs('password.force-change', 'password.force-change.update', 'logout')) {
                return redirect()->route('password.force-change')
                    ->with('warning', 'Anda harus mengganti password sebelum melanjutkan.');
            }
        }

        return $next($request);
    }
}
