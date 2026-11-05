<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyActiveRole
{
    public const SESSION_KEY = 'active_role';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->status !== 'active') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'username' => 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.',
                ]);
            }

            $assigned = $user->assignedRoleNames();

            if (count($assigned) > 1) {
                $active = $request->session()->get(self::SESSION_KEY);

                if (!$active || !in_array($active, $assigned, true)) {
                    $active = $assigned[0];
                    $request->session()->put(self::SESSION_KEY, $active);
                }

                $user->setRelation(
                    'roles',
                    $user->roles()->where('name', $active)->get()
                );
                $user->unsetRelation('permissions');
            } elseif (count($assigned) === 1) {
                $request->session()->put(self::SESSION_KEY, $assigned[0]);
            }

            view()->share([
                'activeRole'    => $request->session()->get(self::SESSION_KEY),
                'assignedRoles' => $assigned,
            ]);
        }

        return $next($request);
    }
}
