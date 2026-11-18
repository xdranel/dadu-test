<?php

namespace App\Http\Controllers;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicDocumentController extends Controller
{
    /**
     * Pastikan user yang login memiliki permission public_document_read.
     */
    protected function authorizeAccess(): void
    {
        if (auth()->check() && ! auth()->user()->can('public_document_read')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengakses Dokumen Publik.');
        }
    }

    /**
     * Cek apakah dokumen dapat diakses di portal publik.
     * Portal publik melayani:
     * 1. Dokumen publik (untuk guest maupun user login).
     * 2. Dokumen kategori mahasiswa KHUSUS untuk mahasiswa login yang NIM-nya tercantum di pivot document_nim.
     * Semua kasus lain (guest, mahasiswa NIM lain, dosen, dokumen non-publik lain) mengembalikan 404 identik.
     */
    protected function checkDocumentAccess(Document $document): void
    {
        if ($document->isPublic()) {
            return;
        }

        if ($document->access_group === AccessGroup::MAHASISWA
            && auth()->check()
            && auth()->user()->hasRole(['mahasiswa', 'Mahasiswa'])
            && ! empty(auth()->user()->nim)
            && $document->nims()->where('nim', (string) auth()->user()->nim)->exists()
        ) {
            return;
        }

        abort(404);
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        // Validasi jika user mengakses folder tertentu lewat parameter URL.
        // Folder non-publik atau folder yang tidak ada mengembalikan 404 identik.
        if ($request->filled('kategori')) {
            $selectedCategory = DocumentCategory::find($request->kategori);
            if (! $selectedCategory || ! $selectedCategory->isPublic()) {
                abort(404);
            }
        }

        // Portal publik strictly hanya melayani dokumen dan folder berkategori publik untuk tampilan umum
        $documents = Document::visibleTo(null)
            ->with('category')
            ->search($request->search)
            ->filterCategory($request->kategori)
            ->when($request->year, function ($q, $y) {
                $q->whereYear('created_at', $y);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Load hirarki folder untuk portal dokumen — hanya kategori publik
        $kategoriList = DocumentCategory::visibleTo(null)
            ->roots()
            ->with(['children' => function ($q) {
                $q->visibleTo(null)
                  ->withCount(['children' => fn($cq) => $cq->visibleTo(null)])
                  ->withCount(['documents' => fn($dq) => $dq->visibleTo(null)])
                  ->pinnedFirst();
            }])
            ->withCount(['children' => function ($q) {
                $q->visibleTo(null);
            }])
            ->withCount(['documents' => function ($q) {
                $q->visibleTo(null);
            }])
            ->pinnedFirst()
            ->get()
            ->filter(function ($kat) {
                $childrenDocCount = $kat->children->sum('documents_count');
                return $kat->documents_count > 0 || $childrenDocCount > 0 || $kat->show_when_empty;
            })
            ->values();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $yearsList = Document::visibleTo(null)
                ->selectRaw("strftime('%Y', created_at) as year")
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        } else {
            $yearsList = Document::visibleTo(null)
                ->selectRaw('YEAR(created_at) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        }

        // Bagian tambahan "Dokumen Saya" untuk mahasiswa login
        $myDocuments = null;
        $myDocumentTypes = collect();
        $isStudent = auth()->check()
            && auth()->user()->hasRole(['mahasiswa', 'Mahasiswa'])
            && ! empty(auth()->user()->nim);

        if ($isStudent) {
            $sessionNim = (string) auth()->user()->nim;

            $myDocumentTypes = Document::query()
                ->where('access_group', AccessGroup::MAHASISWA)
                ->whereHas('nims', fn($q) => $q->where('nim', $sessionNim))
                ->whereNotNull('document_type')
                ->where('document_type', '!=', '')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type');

            $myDocuments = Document::query()
                ->where('access_group', AccessGroup::MAHASISWA)
                ->whereHas('nims', fn($q) => $q->where('nim', $sessionNim))
                ->when($request->filled('document_type'), function ($q) use ($request) {
                    $q->where('document_type', $request->document_type);
                })
                ->when($request->filled('search'), function ($q) use ($request) {
                    $term = $request->search;
                    $q->where(function ($sub) use ($term) {
                        $sub->where('title', 'like', "%{$term}%")
                            ->orWhere('keywords', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%")
                            ->orWhere('document_type', 'like', "%{$term}%");
                    });
                })
                ->when($request->filled('year'), function ($q) use ($request) {
                    $q->whereYear('created_at', $request->year);
                })
                ->latest()
                ->paginate(6, ['*'], 'my_page')
                ->withQueryString();
        }

        return view('public_documents.index', compact('documents', 'kategoriList', 'yearsList', 'myDocuments', 'myDocumentTypes', 'isStudent'));
    }

    public function show(Document $document)
    {
        $this->checkDocumentAccess($document);
        $this->authorizeAccess();

        // Catat akses dokumen mahasiswa di activity log
        if ($document->access_group === AccessGroup::MAHASISWA) {
            activity('student_document_access')
                ->causedBy(auth()->user())
                ->performedOn($document)
                ->withProperties([
                    'action' => 'show',
                    'ip'     => request()->ip(),
                    'nim'    => (string) auth()->user()->nim,
                ])
                ->log('Mahasiswa melihat detail dokumen mahasiswa');
        }

        // Load category with parent for breadcrumb
        $document->load('category.parent');

        $fileMissing = empty($document->file_path) || ! $document->fileExists();

        return view('public_documents.show', compact('document', 'fileMissing'));
    }

    public function preview(Document $document)
    {
        $this->checkDocumentAccess($document);
        $this->authorizeAccess();

        abort_unless($document->file_path && $document->fileExists(), 404, 'File fisik tidak ditemukan di penyimpanan.');
        abort_unless(strtolower($document->file_type) === 'pdf', 415, 'Pratinjau hanya tersedia untuk PDF.');

        // Catat akses dokumen mahasiswa di activity log
        if ($document->access_group === AccessGroup::MAHASISWA) {
            activity('student_document_access')
                ->causedBy(auth()->user())
                ->performedOn($document)
                ->withProperties([
                    'action' => 'preview',
                    'ip'     => request()->ip(),
                    'nim'    => (string) auth()->user()->nim,
                ])
                ->log('Mahasiswa melihat pratinjau dokumen mahasiswa');
        }

        return response()->file($document->getPhysicalPath(), [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $document->getContentDisposition('inline'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=300',
        ]);
    }

    public function download(Document $document)
    {
        $this->checkDocumentAccess($document);
        $this->authorizeAccess();

        if (! $document->fileExists()) {
            abort(404, 'File tidak ditemukan.');
        }

        // Catat akses dokumen mahasiswa di activity log dan JANGAN naikkan download_count
        if ($document->access_group === AccessGroup::MAHASISWA) {
            activity('student_document_access')
                ->causedBy(auth()->user())
                ->performedOn($document)
                ->withProperties([
                    'action' => 'download',
                    'ip'     => request()->ip(),
                    'nim'    => (string) auth()->user()->nim,
                ])
                ->log('Mahasiswa mengunduh dokumen mahasiswa');
        } else {
            // download_count HANYA naik untuk dokumen publik
            $document->increment('download_count');
        }

        return $document->getStorageDiskInstance()->download(
            $document->file_path,
            $document->getDownloadFilename()
        );
    }
}
