<?php

namespace App\Http\Controllers;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use ZipArchive;

class DocumentController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        // Flat view "Semua Dokumen" (lintas folder) — fungsi lama Kelola Dokumen.
        if ($request->view === 'all') {
            return $this->flatIndex($request);
        }

        // Landing view: grid folder (kategori) — hanya root folders.
        $categories = DocumentCategory::roots()
            ->with(['children' => fn($q) => $q->withCount('documents')->withCount('children')->pinnedFirst()])
            ->withCount('documents')
            ->withCount('children')
            ->search($request->search)
            ->pinnedFirst()
            ->get();

        $pinnedLimitReached = DocumentCategory::where('is_pinned', true)->count() >= 5;
        $kategoriList = DocumentCategory::orderBy('name')->get();

        return view('documents.index', compact('categories', 'kategoriList', 'pinnedLimitReached'));
    }

    protected function flatIndex(Request $request)
    {
        $query = Document::with(['category.parent']);

        $documents = $query->search($request->search)
            ->filterCategory($request->kategori)
            ->filterVisibility($request->visibilitas)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $kategoriList = DocumentCategory::orderBy('name')->get();

        return view('documents.all', compact('documents', 'kategoriList'));
    }

    public function moveTargets(Request $request)
    {
        $type = $request->query('type');
        $id = (int) $request->query('id');
        $moving = ($type === 'folder' && $id) ? DocumentCategory::find($id) : null;
        $doc    = ($type === 'doc' && $id) ? Document::find($id) : null;
        $currentParent = $moving ? $moving->parent_id : ($doc ? $doc->document_category_id : null);
        $blocked = $moving ? $moving->descendantIds()->push($moving->id)->flip() : collect();   // set id yang dilarang
        $q = trim((string) $request->query('q', ''));

        $query = DocumentCategory::query()->withCount('children')->orderBy('name');
        $q !== '' ? $query->where('name', 'like', "%{$q}%")->limit(30)
                  : $query->where('parent_id', $request->query('parent_id') ?: null);   // where(col, null) => IS NULL

        $paths = $q !== '' ? DocumentCategory::pathMap() : [];
        $nodes = $query->get()->map(function ($c) use ($blocked, $currentParent, $paths) {
            $reason = $blocked->has($c->id) ? 'Tidak bisa dipindah ke dirinya sendiri atau subfoldernya'
                    : ($c->id === $currentParent ? 'Sudah berada di folder ini' : null);
            return [
                'id'           => $c->id,
                'name'         => $c->name,
                'path'         => $paths[$c->id] ?? $c->name,
                'has_children' => $c->children_count > 0,
                'disabled'     => (bool) $reason,
                'reason'       => $reason,
            ];
        });

        return response()->json([
            'nodes'        => $nodes,
            'rootDisabled' => $type === 'doc' || $currentParent === null,
        ]);
    }

    public function folder(DocumentCategory $documentCategory, Request $request)
    {
        $sort = in_array($request->sort, ['title', 'created_at', 'file_size', 'download_count'], true)
            ? $request->sort : 'created_at';
        $dir = $request->dir === 'asc' ? 'asc' : 'desc';

        $query = $documentCategory->documents()->getQuery();

        $documents = $query->search($request->search)
            ->filterVisibility($request->visibilitas)
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        // Load child folders for this folder with documents and children counts
        $subfolders = $documentCategory->children()
            ->withCount('documents')
            ->withCount('children')
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
        $pinnedLimitReached = DocumentCategory::where('is_pinned', true)->count() >= 5;

        return view('documents.folder', compact(
            'documentCategory', 'documents', 'sort', 'dir',
            'subfolders', 'ancestors', 'allFolders', 'kategoriList', 'pinnedLimitReached'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Document::class);

        $all = DocumentCategory::orderBy('name')->get(['id', 'name', 'parent_id'])->groupBy(fn ($c) => $c->parent_id ?? '');
        $folderOptions = [];
        $walk = function ($parentId, $depth, array $trail) use (&$walk, $all, &$folderOptions) {
            foreach ($all->get($parentId ?? '', collect()) as $c) {
                $path = [...$trail, $c->name];
                $folderOptions[] = ['id' => $c->id, 'name' => $c->name, 'depth' => $depth, 'path' => implode(' / ', $path)];
                $walk($c->id, $depth + 1, $path);
            }
        };
        $walk(null, 0, []);

        $selectedCategory = $request->integer('category') ?: null;
        return view('documents.create', compact('folderOptions', 'selectedCategory'));
    }

    public function store(StoreDocumentRequest $request)
    {
        $this->authorize('create', Document::class);

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

        $saved = 0;
        $failedList = [];
        $dupes = [];

        foreach ($request->file('files', []) as $i => $file) {
            $original = $file->getClientOriginalName();

            if ($file->getSize() > 15 * 1024 * 1024) {
                $failedList[] = ['name' => $original, 'reason' => 'melebihi 15 MB'];
                continue;
            }

            if (! in_array($file->getMimeType(), $allowedMimes, true)) {
                $failedList[] = ['name' => $original, 'reason' => 'tipe file tidak valid (isi file tidak cocok dengan ekstensi)'];
                continue;
            }

            if (Document::where('document_category_id', $request->document_category_id)->where('original_file_name', $original)->exists()) {
                $dupes[] = $original;
            }

            // Tentukan target disk penyimpanan berdasarkan access_group / visibilitas
            $category = $request->document_category_id ? DocumentCategory::find($request->document_category_id) : null;
            $accessGroup = $category?->access_group;
            if (!$accessGroup) {
                $accessGroup = AccessGroup::fromLegacyVisibility($request->visibility);
            }
            $targetDisk = Document::determineStorageDisk($accessGroup, $request->visibility);

            // Sanitize filename to prevent path traversal & keep timestamp prefix
            $safeName    = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
            $storageName = now()->format('YmdHis') . '_' . $safeName;
            $path        = $file->storeAs('dokumen', $storageName, $targetDisk);
            $checksum    = hash_file('sha256', $file->getRealPath());

            $title = $request->input("judul.{$i}");
            if (blank($title)) {
                $title = \Illuminate\Support\Str::of(pathinfo($original, PATHINFO_FILENAME))
                    ->replace(['-', '_'], ' ')->squish()->title()->value();
            }

            Document::create([
                'document_category_id' => $request->document_category_id,
                'title'                => $title,
                'abbreviation'         => strtoupper((string) $request->input("singkatan.{$i}", '')),
                'description'          => $request->input("deskripsi.{$i}"),
                'file_name'            => $original,
                'original_file_name'   => $original,
                'file_path'            => $path,
                'storage_disk'         => $targetDisk,
                'checksum'             => $checksum,
                'file_type'            => strtolower($file->getClientOriginalExtension()),
                'file_size'            => $file->getSize(),
                'visibility'           => $request->visibility,
                'access_group'         => $accessGroup instanceof AccessGroup ? $accessGroup->value : $accessGroup,
            ]);

            $saved++;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'saved'  => $saved,
                'failed' => collect($failedList)->map(fn ($f) => ['name' => $f['name'], 'reason' => $f['reason']])->values(),
            ], $saved > 0 ? 200 : 422);
        }

        $failed = array_map(fn ($f) => "{$f['name']} - {$f['reason']}", $failedList);

        if ($saved === 0) {
            return back()->withInput()->withErrors(['files' => "Tidak ada file yang berhasil diunggah:\n- " . implode("\n- ", $failed)]);
        }

        // Validasi redirect_to agar aman dari open redirect (hanya URL internal)
        $defaultRedirect = $request->filled('document_category_id')
            ? route('documents.folder', $request->document_category_id)
            : route('documents.index');

        $redirectTo = $request->input('redirect_to');
        if (!empty($redirectTo) && str_starts_with($redirectTo, url('/'))) {
            $targetUrl = $redirectTo;
        } else {
            $targetUrl = $defaultRedirect;
        }

        $redirect = redirect($targetUrl)->with('success', "{$saved} dokumen berhasil diunggah.");

        $warn = [];
        if ($failed) {
            $warn[] = count($failed) . " file gagal:\n- " . implode("\n- ", $failed);
        }
        if ($dupes) {
            $warn[] = "Nama file sama sudah ada di folder ini (tetap diunggah):\n- " . implode("\n- ", $dupes);
        }
        if ($warn) {
            $redirect->with('warning', implode("\n\n", $warn));
        }

        return $redirect;
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        // Eager load category with ancestors for breadcrumb
        $document->load('category.parent');

        $fileMissing = empty($document->file_path) || ! $document->fileExists();

        return view('documents.show', compact('document', 'fileMissing'));
    }

    public function preview(Document $document)
    {
        $this->authorize('view', $document);

        abort_unless($document->file_path && $document->fileExists(), 404, 'File fisik tidak ditemukan di penyimpanan.');
        abort_unless(strtolower($document->file_type) === 'pdf', 415, 'Pratinjau hanya tersedia untuk PDF.');

        // Audit unduhan / pratinjau untuk dokumen non-publik
        if (! $document->isPublic()) {
            activity('document_access')
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->withProperties([
                    'action'       => 'preview',
                    'document_id'  => $document->id,
                    'title'        => $document->title,
                    'access_group' => $document->access_group instanceof AccessGroup ? $document->access_group->value : ($document->access_group ?? $document->visibility),
                    'ip'           => request()->ip(),
                    'user_agent'   => request()->userAgent(),
                ])
                ->log("Pratinjau dokumen non-publik: {$document->title}");
        }

        return response()->file($document->getPhysicalPath(), [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $document->getContentDisposition('inline'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=300',
        ]);
    }

    public function edit(Document $document)
    {
        $kategoriList = DocumentCategory::orderBy('name')->get();
        return view('documents.edit', compact('document', 'kategoriList'));
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $this->authorize('update', $document);

        $data = $request->safe()->except(['file', 'nims']);

        if ($request->hasFile('file')) {
            $currentDisk = $document->getStorageDisk();
            if (!empty($document->file_path) && Storage::disk($currentDisk)->exists($document->file_path)) {
                Storage::disk($currentDisk)->delete($document->file_path);
            }

            $targetDisk   = Document::determineStorageDisk($document->access_group, $document->visibility);
            $file         = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $safeName     = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $storageName  = now()->format('YmdHis') . '_' . $safeName;

            $data['file_path']          = $file->storeAs('dokumen', $storageName, $targetDisk);
            $data['storage_disk']       = $targetDisk;
            $data['checksum']           = hash_file('sha256', $file->getRealPath());
            $data['file_name']          = $originalName;
            $data['original_file_name'] = $originalName;
            $data['file_type']          = strtolower($file->getClientOriginalExtension());
            $data['file_size']          = $file->getSize();
        }

        if (isset($data['abbreviation'])) {
            $data['abbreviation'] = strtoupper($data['abbreviation']);
        }

        $document->update($data);

        if ($request->has('nims')) {
            $rawNims = $request->input('nims');
            $nimsArray = is_array($rawNims)
                ? $rawNims
                : array_filter(preg_split('/[;, \r\n]+/', trim((string) $rawNims)), fn($v) => $v !== '');
            $document->syncNims($nimsArray, auth()->user());
        }

        $msg = "Dokumen {$document->title} berhasil diperbarui.";

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }

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
            $now = now();
            Document::whereIn('id', $docIds)->chunkById(200, function ($docs) {
                $docs->each->delete();
            });
            Document::whereIn('id', $docIds)->update(['deleted_at' => $now]);
        }

        $totalFolders = count($folderIds);
        $totalDocs    = count($docIds);
        $msg = [];
        if ($totalFolders) $msg[] = "{$totalFolders} folder";
        if ($totalDocs)    $msg[] = "{$totalDocs} dokumen";

        $successMessage = (!empty($msg) ? implode(' dan ', $msg) : 'Item terpilih') . ' berhasil dihapus (dapat dipulihkan).';

        $undo = null;
        if ($totalFolders === 1 && $totalDocs === 0) {
            $undo = ['url' => route('document_categories.restore', reset($folderIds)), 'label' => 'Urungkan'];
        } elseif ($totalDocs === 1 && $totalFolders === 0) {
            $undo = ['url' => route('documents.restore', reset($docIds)), 'label' => 'Urungkan'];
        } elseif ($totalFolders > 0 || $totalDocs > 0) {
            $undo = [
                'url' => route('documents.bulk-restore'),
                'label' => 'Urungkan',
                'payload' => ['doc_ids' => array_values($docIds), 'folder_ids' => array_values($folderIds)],
            ];
        }

        $response = $request->filled('redirect_to')
            ? redirect($request->redirect_to)->with('success', $successMessage)
            : redirect()->back()->with('success', $successMessage);

        return $undo ? $response->with('undo', $undo) : $response;
    }

    public function bulkRestore(Request $request)
    {
        $this->authorize('delete', Document::class);

        $docIds    = array_filter($request->input('doc_ids', []));
        $folderIds = array_filter($request->input('folder_ids', []));

        DB::transaction(function () use ($docIds, $folderIds) {
            foreach ($folderIds as $folderId) {
                $category = DocumentCategory::onlyTrashed()->find($folderId);
                if ($category) {
                    $deletedAt = $category->deleted_at;
                    $category->restore();

                    if ($deletedAt) {
                        $descendantIds = $category->descendantIds();
                        if ($descendantIds->isNotEmpty()) {
                            DocumentCategory::onlyTrashed()
                                ->whereIn('id', $descendantIds)
                                ->where('deleted_at', $deletedAt)
                                ->restore();
                        }

                        $allCategoryIds = $category->descendantIds()->push($category->id);
                        Document::onlyTrashed()
                            ->whereIn('document_category_id', $allCategoryIds)
                            ->where('deleted_at', $deletedAt)
                            ->chunkById(200, function ($docs) {
                                $docs->each->restore();
                            });
                    }
                }
            }

            if (!empty($docIds)) {
                Document::onlyTrashed()
                    ->whereIn('id', $docIds)
                    ->chunkById(200, function ($docs) {
                        $docs->each->restore();
                    });
            }
        });

        return back()->with('success', 'Item terpilih berhasil dipulihkan.');
    }

    public function bulkDownload(Request $request)
    {
        @set_time_limit(300);

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

        $user = auth()->user();

        if (count($folderIds) === 0 && count($docIds) === 1) {
            $doc = Document::visibleTo($user)->find(reset($docIds));
            if ($doc && $user?->can('download', $doc) && $doc->fileExists()) {
                if (! $doc->isPublic()) {
                    activity('document_access')
                        ->performedOn($doc)
                        ->causedBy($user)
                        ->withProperties([
                            'action'       => 'download',
                            'document_id'  => $doc->id,
                            'title'        => $doc->title,
                            'access_group' => $doc->access_group instanceof AccessGroup ? $doc->access_group->value : ($doc->access_group ?? $doc->visibility),
                            'ip'           => request()->ip(),
                            'user_agent'   => request()->userAgent(),
                        ])
                        ->log("Unduh dokumen non-publik: {$doc->title}");
                }

                $doc->increment('download_count');
                return $doc->getStorageDiskInstance()->download($doc->file_path, $doc->file_name);
            }
            return back()->withErrors(['download' => 'File tidak ditemukan di server atau Anda tidak memiliki akses.']);
        }

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
        $totalFiles = 0;
        $totalBytes = 0;
        $nonPublicIncludedDocs = [];

        try {
            $folders = DocumentCategory::visibleTo($user)->whereIn('id', $folderIds)->get();
            foreach ($folders as $folder) {
                if ($user?->can('view', $folder)) {
                    $this->addCategoryRecursivelyToZip(
                        $zip, $folder, '', $addedDocIds, $usedPaths, $totalFiles, $totalBytes, $nonPublicIncludedDocs
                    );
                }
            }

            $looseDocIds = array_diff($docIds, $addedDocIds);
            if (!empty($looseDocIds)) {
                $looseDocs = Document::visibleTo($user)->whereIn('id', $looseDocIds)->get();
                foreach ($looseDocs as $doc) {
                    if (!$user?->can('view', $doc) || !$doc->fileExists()) {
                        continue;
                    }

                    $fileSize = (int) $doc->getStorageDiskInstance()->size($doc->file_path);
                    if ($totalFiles >= 500 || ($totalBytes + $fileSize) > 524288000) {
                        throw new \RuntimeException('Jumlah file atau ukuran total melebihi batas unduhan massal (maksimal 500 file / 500 MB).');
                    }

                    $realPath  = $doc->getPhysicalPath();
                    $entryName = $this->getUniqueZipPath('', $doc->getDownloadFilename(), $usedPaths);
                    $zip->addFile($realPath, $entryName);
                    $doc->increment('download_count');

                    if (! $doc->isPublic()) {
                        $nonPublicIncludedDocs[] = $doc;
                    }

                    $totalFiles++;
                    $totalBytes += $fileSize;
                }
            }

            $numFiles = $zip->numFiles;
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return back()->withErrors(['download' => $e->getMessage()]);
        }

        if ($numFiles === 0 && empty($folderIds)) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return back()->withErrors(['download' => 'Tidak ada file atau folder yang dapat diunduh.']);
        }

        // Audit unduhan massal bila memuat dokumen non-publik
        if (!empty($nonPublicIncludedDocs)) {
            activity('document_access')
                ->causedBy($user)
                ->withProperties([
                    'action'             => 'bulk_download_zip',
                    'document_ids'       => collect($nonPublicIncludedDocs)->pluck('id')->values()->all(),
                    'count'              => count($nonPublicIncludedDocs),
                    'total_files_in_zip' => $totalFiles,
                    'ip'                 => request()->ip(),
                    'user_agent'         => request()->userAgent(),
                ])
                ->log('Unduh massal ZIP memuat ' . count($nonPublicIncludedDocs) . ' dokumen non-publik');
        }

        return response()->download($zipPath, 'dokumen_' . now()->format('Ymd_His') . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Add a category folder and all its documents and subfolders recursively to ZIP.
     * Dilengkapi batasan panjang path Windows (260 char limit), sanitasi karakter ilegal,
     * serta batas jumlah dan ukuran file.
     */
    private function addCategoryRecursivelyToZip(
        ZipArchive $zip,
        DocumentCategory $category,
        string $parentPath,
        array &$addedDocIds,
        array &$usedPaths,
        int &$totalFiles,
        int &$totalBytes,
        array &$nonPublicIncludedDocs = []
    ): void {
        $user = auth()->user();
        if ($user && ! $user->can('view', $category)) {
            return;
        }

        $safeName = $this->sanitizeZipSegment($category->name, 80);
        $folderPath = ($parentPath !== '' ? $parentPath . '/' : '') . $safeName;

        $zip->addEmptyDir($folderPath);

        $documents = Document::visibleTo($user)->where('document_category_id', $category->id)->get();
        foreach ($documents as $doc) {
            if ($user && ! $user->can('view', $doc)) {
                continue;
            }

            if (!$doc->fileExists()) {
                continue;
            }

            $fileSize = (int) $doc->getStorageDiskInstance()->size($doc->file_path);
            if ($totalFiles >= 500 || ($totalBytes + $fileSize) > 524288000) {
                throw new \RuntimeException('Jumlah file atau ukuran total melebihi batas unduhan massal (maksimal 500 file / 500 MB).');
            }

            $realPath  = $doc->getPhysicalPath();
            $entryName = $this->getUniqueZipPath($folderPath, $doc->getDownloadFilename(), $usedPaths);
            $zip->addFile($realPath, $entryName);
            $addedDocIds[] = $doc->id;
            $doc->increment('download_count');

            if (! $doc->isPublic()) {
                $nonPublicIncludedDocs[] = $doc;
            }

            $totalFiles++;
            $totalBytes += $fileSize;
        }

        $children = DocumentCategory::visibleTo($user)->where('parent_id', $category->id)->get();
        foreach ($children as $child) {
            $this->addCategoryRecursivelyToZip(
                $zip, $child, $folderPath, $addedDocIds, $usedPaths, $totalFiles, $totalBytes, $nonPublicIncludedDocs
            );
        }
    }

    /**
     * Sanitasi segmen nama folder untuk ZIP (maks 80 karakter, hilangkan karakter ilegal).
     */
    private function sanitizeZipSegment(string $name, int $maxLength = 80): string
    {
        $cleaned = preg_replace('~[\\/:\*\?"<>\|]~', '_', $name);
        $cleaned = trim($cleaned, ". \t\n\r\0\x0B");
        if ($cleaned === '') {
            $cleaned = 'folder';
        }
        if (mb_strlen($cleaned) > $maxLength) {
            $cleaned = mb_substr($cleaned, 0, $maxLength);
        }
        return $cleaned;
    }

    /**
     * Sanitasi nama file dokumen untuk ZIP (maks 80 karakter nama file, pertahankan ekstensi).
     */
    private function sanitizeZipFilename(string $fileName, int $maxLength = 80): string
    {
        $info = pathinfo($fileName);
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $nameOnly = $info['filename'] ?? $fileName;
        $cleanedName = preg_replace('~[\\/:\*\?"<>\|]~', '_', $nameOnly);
        $cleanedName = trim($cleanedName, ". \t\n\r\0\x0B");
        if ($cleanedName === '') {
            $cleanedName = 'dokumen';
        }
        $maxNameLen = max(10, $maxLength - mb_strlen($ext));
        if (mb_strlen($cleanedName) > $maxNameLen) {
            $cleanedName = mb_substr($cleanedName, 0, $maxNameLen);
        }
        return $cleanedName . $ext;
    }

    /**
     * Generate unique path inside ZIP to avoid duplicate name collisions.
     * Mencegah panjang path melebihi 240 karakter agar aman di sistem operasi Windows.
     */
    private function getUniqueZipPath(string $dirPath, string $fileName, array &$usedPaths): string
    {
        $safeFileName = $this->sanitizeZipFilename($fileName, 80);

        $dirLen = mb_strlen($dirPath);
        if ($dirLen > 0 && ($dirLen + 1 + mb_strlen($safeFileName)) > 240) {
            $allowedFileLen = max(12, 240 - $dirLen - 1);
            $safeFileName = $this->sanitizeZipFilename($fileName, $allowedFileLen);
        }

        $baseName = $dirPath !== '' ? $dirPath . '/' . $safeFileName : $safeFileName;
        if (!isset($usedPaths[$baseName])) {
            $usedPaths[$baseName] = 0;
            return $baseName;
        }

        $usedPaths[$baseName]++;
        $info = pathinfo($safeFileName);
        $nameOnly = $info['filename'] ?? $safeFileName;
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $uniqueName = "{$nameOnly}_{$usedPaths[$baseName]}{$ext}";

        return $dirPath !== '' ? $dirPath . '/' . $uniqueName : $uniqueName;
    }

    /**
     * Cascade soft-delete a DocumentCategory and all its contents secara iteratif dalam satu transaksi.
     * Memberikan deleted_at yang seragam untuk seluruh item agar aman untuk undo/restore,
     * serta tetap memicu DocumentObserver::deleted via chunkById.
     */
    private function cascadeSoftDeleteCategory(DocumentCategory $category): void
    {
        DB::transaction(function () use ($category) {
            $descendantIds = $category->descendantIds();
            $allCategoryIds = $descendantIds->push($category->id);
            $now = now();

            Document::whereIn('document_category_id', $allCategoryIds)
                ->chunkById(200, function ($docs) {
                    $docs->each->delete();
                });

            Document::whereIn('document_category_id', $allCategoryIds)->update(['deleted_at' => $now]);
            DocumentCategory::whereIn('id', $allCategoryIds)->update(['deleted_at' => $now]);
        });
    }

    public function setVisibility(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'visibility' => ['required', Rule::in(array_keys(config('documents.visibility')))],
        ]);

        $document->update($data);

        return response()->json(['ok' => true]);
    }

    public function restore($id)
    {
        $doc = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $doc);
        $doc->restore();

        return back()->with('success', "Dokumen \"{$doc->title}\" berhasil dipulihkan.");
    }

    public function destroy(Document $document, Request $request)
    {
        $catId = $document->document_category_id;
        $title = $document->title;
        $docId = $document->id;

        // Soft delete — file fisik tetap ada untuk recovery
        $document->delete();

        $msg = "Dokumen \"{$title}\" berhasil dihapus (dapat dipulihkan).";
        $undo = [
            'url' => route('documents.restore', $docId),
            'label' => 'Urungkan',
        ];

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg)->with('undo', $undo);
        }

        if ($catId) {
            return redirect()->route('documents.folder', $catId)->with('success', $msg)->with('undo', $undo);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg)
            ->with('undo', $undo);
    }

    public function download(Document $document)
    {
        $this->authorize('download', $document);

        if (! $document->fileExists()) {
            abort(404, 'File tidak ditemukan.');
        }

        // Audit unduhan untuk dokumen non-publik
        if (! $document->isPublic()) {
            activity('document_access')
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->withProperties([
                    'action'       => 'download',
                    'document_id'  => $document->id,
                    'title'        => $document->title,
                    'access_group' => $document->access_group instanceof AccessGroup ? $document->access_group->value : ($document->access_group ?? $document->visibility),
                    'ip'           => request()->ip(),
                    'user_agent'   => request()->userAgent(),
                ])
                ->log("Unduh dokumen non-publik: {$document->title}");
        }

        $document->increment('download_count');

        return $document->getStorageDiskInstance()->download(
            $document->file_path,
            $document->getDownloadFilename()
        );
    }
}
