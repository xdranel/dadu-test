<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicDocumentController extends Controller
{
    /**
     * Dapatkan visibilitas dokumen/folder yang diizinkan untuk user/guest saat ini:
     * - Guest & Mahasiswa : ['publik']
     * - Dosen, Pegawai, Superadmin : ['publik', 'internal']
     */
    protected function getAllowedVisibilities(): array
    {
        $user = auth()->user();
        $canViewInternal = $user && $user->hasRole(['Akademik', 'admin', 'Staf SSC', 'dosen']);

        return $canViewInternal ? ['publik', 'internal'] : ['publik'];
    }

    public function index(Request $request)
    {
        $allowedVisibilities = $this->getAllowedVisibilities();

        // Validasi jika user mengakses folder tertentu lewat parameter URL
        if ($request->filled('kategori')) {
            $selectedCategory = DocumentCategory::find($request->kategori);
            if ($selectedCategory && !in_array(strtolower($selectedCategory->visibility ?? 'publik'), $allowedVisibilities)) {
                abort(403, 'Anda tidak memiliki akses ke folder ini.');
            }
        }

        $documents = Document::with('category')
            ->whereIn('visibility', $allowedVisibilities)
            ->search($request->search)
            ->filterCategory($request->kategori)
            ->when($request->year, function ($q, $y) {
                $q->whereYear('created_at', $y);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Load hirarki folder untuk portal dokumen — difilter sesuai visibilitas yang diizinkan
        $kategoriList = DocumentCategory::roots()
            ->whereIn('visibility', $allowedVisibilities)
            ->with(['children' => function ($q) use ($allowedVisibilities) {
                $q->whereIn('visibility', $allowedVisibilities)
                  ->withCount(['documents' => fn($dq) => $dq->whereIn('visibility', $allowedVisibilities)])
                  ->pinnedFirst();
            }])
            ->withCount(['documents' => function ($q) use ($allowedVisibilities) {
                $q->whereIn('visibility', $allowedVisibilities);
            }])
            ->pinnedFirst()
            ->get()
            ->filter(function ($kat) {
                // Tampilkan jika memiliki dokumen yang diizinkan atau toggle show_when_empty aktif
                $childrenDocCount = $kat->children->sum('documents_count');
                return $kat->documents_count > 0 || $childrenDocCount > 0 || $kat->show_when_empty;
            })
            ->values();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $yearsList = Document::whereIn('visibility', $allowedVisibilities)
                ->selectRaw("strftime('%Y', created_at) as year")
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        } else {
            $yearsList = Document::whereIn('visibility', $allowedVisibilities)
                ->selectRaw('YEAR(created_at) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        }

        return view('public_documents.index', compact('documents', 'kategoriList', 'yearsList'));
    }

    public function show(Document $document)
    {
        $allowedVisibilities = $this->getAllowedVisibilities();

        if (!in_array(strtolower($document->visibility ?? ''), $allowedVisibilities)) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        // Load category with parent for breadcrumb
        $document->load('category.parent');

        return view('public_documents.show', compact('document'));
    }

    public function download(Document $document)
    {
        $allowedVisibilities = $this->getAllowedVisibilities();

        if (!in_array(strtolower($document->visibility ?? ''), $allowedVisibilities)) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $document->increment('download_count');

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name
        );
    }
}
