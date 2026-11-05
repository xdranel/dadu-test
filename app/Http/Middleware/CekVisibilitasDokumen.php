<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CekVisibilitasDokumen
{
    public function handle(Request $request, Closure $next)
    {
        $dokumen = $request->route('dokumen') ?? $request->route('document');

        if (!$dokumen) return $next($request);

        $user         = auth()->user();
        $visibilitas  = strtolower($dokumen->visibility ?? $dokumen->visibilitas ?? 'publik');

        // Visibilitas publik dapat diakses siapapun (guest & mahasiswa)
        if ($visibilitas === 'publik') return $next($request);

        // Visibilitas internal mewajibkan login
        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'Silakan login untuk mengakses dokumen ini.');
        }

        // Superadmin, Admin, Staf SSC, Dosen berhak mengakses dokumen internal
        if ($user->hasRole(['Akademik', 'admin', 'Staf SSC', 'dosen'])) {
            return $next($request);
        }

        // Mahasiswa atau role lain yang tidak diizinkan
        abort(403, 'Dokumen ini hanya dapat diakses oleh Pegawai dan Dosen.');
    }
}