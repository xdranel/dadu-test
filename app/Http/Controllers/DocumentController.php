<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        // Flat view "Semua Dokumen" (lintas folder) — fungsi lama Kelola Dokumen.
        if ($request->view === 'all') {
            return $this->flatIndex($request);
        }

        // Landing view: grid folder (kategori) — hanya root folders.
        $categories = DocumentCategory::roots()
            ->with(['children' => fn($q) => $q->withCount('documents')->pinnedFirst()])
            ->withCount('documents')
            ->search($request->search)
            ->pinnedFirst()
            ->get();

        $kategoriList = DocumentCategory::orderBy('name')->get();

        return view('documents.index', compact('categories', 'kategoriList'));
    }

    protected function flatIndex(Request $request)
    {
        $query = Document::with('category');

        $documents = $query->search($request->search)
            ->filterCategory($request->kategori)
            ->filterVisibility($request->visibilitas)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $kategoriList = DocumentCategory::orderBy('name')->get();

        return view('documents.all', compact('documents', 'kategoriList'));
    }

    public function folder(DocumentCategory $documentCategory, Request $request)
    {
        $sort = in_array($request->sort, ['title', 'created_at', 'file_size', 'download_count'])
            ? $request->sort : 'created_at';
        $dir = $request->dir === 'asc' ? 'asc' : 'desc';

        $query = $documentCategory->documents()->getQuery();

        $documents = $query->search($request->search)
            ->filterVisibility($request->visibilitas)
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        // Load child folders for this folder, optionally filtered by search term
        $subfolders = $documentCategory->children()
            ->withCount('documents')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->pinnedFirst()
            ->get();

        // Build breadcrumb ancestors
        $ancestors = $documentCategory->ancestors;

        // All folders for move modal (exclude self and descendants)
        $allFolders = DocumentCategory::roots()
            ->with('children')
            ->orderBy('name')
            ->get();

        // Kategori list for detail panel and selection dropdowns
        $kategoriList = DocumentCategory::orderBy('name')->get();

        return view('documents.folder', compact(
            'documentCategory', 'documents', 'sort', 'dir',
            'subfolders', 'ancestors', 'allFolders', 'kategoriList'
        ));
    }

    public function create(Request $request)
    {
        $kategoriList = DocumentCategory::orderBy('name')->get();
        $selectedCategory = $request->category;
        return view('documents.create', compact('kategoriList', 'selectedCategory'));
    }

    public function store(StoreDocumentRequest $request)
    {
        $uploaded = 0;

        foreach ($request->file('files') as $index => $file) {
            $originalName = $file->getClientOriginalName();
            $extension    = $file->getClientOriginalExtension();

            // Sanitize filename to prevent path traversal
            $safeName     = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $storageName  = now()->format('YmdHis') . '_' . $safeName;
            $path         = $file->storeAs('dokumen', $storageName, 'public');

            // Validate actual MIME type (not just extension)
            $mimeType = $file->getMimeType();
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/x-rar-compressed',
                'application/vnd.rar',
                'application/octet-stream', // fallback for some RAR files
            ];

            if (!in_array($mimeType, $allowedMimes)) {
                // Remove the uploaded file and skip
                Storage::disk('public')->delete($path);
                continue;
            }

            $title = $request->input("judul.{$index}");
            if (blank($title)) {
                $title = \Illuminate\Support\Str::of(pathinfo($originalName, PATHINFO_FILENAME))
                    ->replace(['-', '_'], ' ')->squish()->title()->value();
            }

            Document::create([
                'document_category_id' => $request->document_category_id,
                'title'                => $title,
                'abbreviation'         => strtoupper((string) $request->input("singkatan.{$index}", '')),
                'description'          => $request->input("deskripsi.{$index}"),
                'file_name'            => $originalName,
                'original_file_name'   => $originalName,
                'file_path'            => $path,
                'file_type'            => strtolower($extension),
                'file_size'            => $file->getSize(),
                'visibility'           => $request->visibility,
            ]);

            $uploaded++;
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', "{$uploaded} dokumen berhasil diunggah.");
        }

        $redirect = $request->filled('document_category_id')
            ? redirect()->route('documents.folder', $request->document_category_id)
            : redirect()->route('documents.index');

        return $redirect->with('success', "{$uploaded} dokumen berhasil diunggah.");
    }

    public function show(Document $document)
    {
        // Eager load category with ancestors for breadcrumb
        $document->load('category.parent');

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $kategoriList = DocumentCategory::orderBy('name')->get();
        return view('documents.edit', compact('document', 'kategoriList'));
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $file         = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $safeName     = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $storageName  = now()->format('YmdHis') . '_' . $safeName;

            $data['file_path']   = $file->storeAs('dokumen', $storageName, 'public');
            $data['file_name']   = $originalName;
            $data['original_file_name'] = $originalName;
            $data['file_type']   = strtolower($file->getClientOriginalExtension());
            $data['file_size'] = $file->getSize();
        }

        if (isset($data['abbreviation'])) {
            $data['abbreviation'] = strtoupper($data['abbreviation']);
        }

        $document->update($data);

        $msg = "Dokumen {$document->title} berhasil diperbarui.";

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        if ($document->document_category_id) {
            return redirect()->route('documents.folder', $document->document_category_id)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'doc_ids'       => 'nullable|array',
            'doc_ids.*'     => 'integer|exists:documents,id',
            'folder_ids'    => 'nullable|array',
            'folder_ids.*'  => 'integer|exists:document_categories,id',
            'redirect_to'   => 'nullable|string',
        ]);

        $docIds    = array_filter($request->input('doc_ids', []));
        $folderIds = array_filter($request->input('folder_ids', []));

        // Hapus folder beserta isinya (cascade soft-delete)
        foreach ($folderIds as $folderId) {
            $folder = DocumentCategory::find($folderId);
            if ($folder) {
                $this->cascadeSoftDeleteCategory($folder);
            }
        }

        // Hapus dokumen individual
        if (!empty($docIds)) {
            Document::whereIn('id', $docIds)->each(fn ($doc) => $doc->delete());
        }

        $totalFolders = count($folderIds);
        $totalDocs    = count($docIds);
        $msg = [];
        if ($totalFolders) $msg[] = "{$totalFolders} folder";
        if ($totalDocs)    $msg[] = "{$totalDocs} dokumen";

        $successMessage = (!empty($msg) ? implode(' dan ', $msg) : 'Item terpilih') . ' berhasil dihapus (dapat dipulihkan).';

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $successMessage);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    public function bulkDownload(Request $request)
    {
        $request->validate([
            'doc_ids'       => 'nullable|array',
            'doc_ids.*'     => 'integer|exists:documents,id',
            'folder_ids'    => 'nullable|array',
            'folder_ids.*'  => 'integer|exists:document_categories,id',
        ]);

        $docIds    = array_filter($request->input('doc_ids', []));
        $folderIds = array_filter($request->input('folder_ids', []));

        if (empty($docIds) && empty($folderIds)) {
            return back()->withErrors(['download' => 'Pilih setidaknya satu dokumen atau folder untuk diunduh.']);
        }

        // Unduh langsung HANYA jika murni 1 dokumen dan 0 folder dipilih
        if (count($folderIds) === 0 && count($docIds) === 1) {
            $doc = Document::find(reset($docIds));
            if ($doc && Storage::disk('public')->exists($doc->file_path)) {
                $doc->increment('download_count');
                return Storage::disk('public')->download($doc->file_path, $doc->file_name);
            }
            return back()->withErrors(['download' => 'File tidak ditemukan di server.']);
        }

        // Buat ZIP dengan mempertahankan hierarki folder asli secara rekursif
        $tmpDir  = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir . '/bulk_' . now()->format('YmdHis') . '_' . auth()->id() . '_' . \Illuminate\Support\Str::random(6) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['download' => 'Gagal membuat file ZIP.']);
        }

        $addedDocIds = [];
        $usedPaths = [];

        // 1. Tambahkan folder-folder yang dipilih secara rekursif
        $folders = DocumentCategory::whereIn('id', $folderIds)->get();
        foreach ($folders as $folder) {
            $this->addCategoryRecursivelyToZip($zip, $folder, '', $addedDocIds, $usedPaths);
        }

        // 2. Tambahkan dokumen lepas di root ZIP
        $looseDocIds = array_diff($docIds, $addedDocIds);
        if (!empty($looseDocIds)) {
            $looseDocs = Document::whereIn('id', $looseDocIds)->get();
            foreach ($looseDocs as $doc) {
                if (!Storage::disk('public')->exists($doc->file_path)) {
                    continue;
                }
                $realPath  = Storage::disk('public')->path($doc->file_path);
                $entryName = $this->getUniqueZipPath('', $doc->file_name, $usedPaths);
                $zip->addFile($realPath, $entryName);
                $doc->increment('download_count');
            }
        }

        $numFiles = $zip->numFiles;
        $zip->close();

        if ($numFiles === 0 && empty($folderIds)) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            return back()->withErrors(['download' => 'Tidak ada file atau folder yang dapat diunduh.']);
        }

        return response()->download($zipPath, 'dokumen_' . now()->format('Ymd_His') . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Add a category folder and all its documents and subfolders recursively to ZIP.
     */
    private function addCategoryRecursivelyToZip(
        ZipArchive $zip,
        DocumentCategory $category,
        string $parentPath,
        array &$addedDocIds,
        array &$usedPaths
    ): void {
        $folderPath = ($parentPath !== '' ? $parentPath . '/' : '') . $category->name;

        // Daftarkan folder ke ZIP (wajib addEmptyDir agar folder kosong tetap berwujud direktori)
        $zip->addEmptyDir($folderPath);

        // Masukkan dokumen dalam folder ini
        $documents = $category->documents;
        foreach ($documents as $doc) {
            if (!Storage::disk('public')->exists($doc->file_path)) {
                continue;
            }
            $realPath  = Storage::disk('public')->path($doc->file_path);
            $entryName = $this->getUniqueZipPath($folderPath, $doc->file_name, $usedPaths);
            $zip->addFile($realPath, $entryName);
            $addedDocIds[] = $doc->id;
            $doc->increment('download_count');
        }

        // Rekursif ke seluruh subfolder anak
        $children = $category->children;
        foreach ($children as $child) {
            $this->addCategoryRecursivelyToZip($zip, $child, $folderPath, $addedDocIds, $usedPaths);
        }
    }

    /**
     * Generate unique path inside ZIP to avoid duplicate name collisions.
     */
    private function getUniqueZipPath(string $dirPath, string $fileName, array &$usedPaths): string
    {
        $baseName = $dirPath !== '' ? $dirPath . '/' . $fileName : $fileName;
        if (!isset($usedPaths[$baseName])) {
            $usedPaths[$baseName] = 0;
            return $baseName;
        }

        $usedPaths[$baseName]++;
        $info = pathinfo($fileName);
        $nameOnly = $info['filename'] ?? $fileName;
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $uniqueName = "{$nameOnly}_{$usedPaths[$baseName]}{$ext}";

        return $dirPath !== '' ? $dirPath . '/' . $uniqueName : $uniqueName;
    }

    /**
     * Cascade soft-delete a DocumentCategory and all its contents.
     */
    private function cascadeSoftDeleteCategory(DocumentCategory $category): void
    {
        $category->documents()->each(fn ($doc) => $doc->delete());
        $category->children()->each(function ($child) {
            $this->cascadeSoftDeleteCategory($child);
        });
        $category->delete();
    }

    public function destroy(Document $document, Request $request)
    {
        $catId = $document->document_category_id;
        $title = $document->title;

        // Soft delete — file fisik tetap ada untuk recovery
        $document->delete();

        $msg = "Dokumen \"{$title}\" berhasil dihapus (dapat dipulihkan).";

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        if ($catId) {
            return redirect()->route('documents.folder', $catId)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    public function download(Document $document)
    {

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
