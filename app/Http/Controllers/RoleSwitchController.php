<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyActiveRole;
use Illuminate\Http\Request;

class RoleSwitchController extends Controller
{
    /**
     * Ganti role aktif untuk sesi saat ini (hanya ke role yang dimiliki user).
     */
    public function switch(Request $request)
    {
        $request->validate([
            'role' => ['required', 'string'],
        ]);

        $user = $request->user();
        $role = $request->input('role');

        if (!in_array($role, $user->assignedRoleNames(), true)) {
            abort(403, 'Anda tidak memiliki role tersebut.');
        }

        $request->session()->put(ApplyActiveRole::SESSION_KEY, $role);
        return redirect()->route('dashboard')
            ->with('success', "Peran aktif diganti ke {$role}.");
    }
}
