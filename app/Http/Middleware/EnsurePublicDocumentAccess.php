<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicDocumentAccess
{
    /**
     * Pastikan pengguna terautentikasi memiliki permission public_document_read.
     * Pengguna tamu (guest) tetap dapat melihat direktori dokumen publik.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! $request->user()->can('public_document_read')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengakses Dokumen Publik.');
        }

        return $next($request);
    }
}
