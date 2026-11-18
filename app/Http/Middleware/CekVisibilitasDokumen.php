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

        // Visibilitas publik: jika login, harus memiliki permission public_document_read
        if ($visibilitas === 'publik') {
            if ($user && ! $user->can('public_document_read')) {
                abort(403, 'Anda tidak memiliki hak akses untuk mengakses Dokumen Publik.');
            }
            return $next($request);
        }

        // Visibilitas internal mewajibkan login
        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'Silakan login untuk mengakses dokumen ini.');
        }

        // Pengguna dengan permission document_read berhak mengakses dokumen internal
        if ($user->can('document_read')) {
            return $next($request);
        }

        // Role atau user tanpa permission document_read
        abort(403, 'Dokumen ini hanya dapat diakses oleh pengguna yang memiliki hak akses Dokumen Internal.');
    }
}