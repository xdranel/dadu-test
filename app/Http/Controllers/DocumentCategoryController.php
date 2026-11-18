<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Http\Requests\MoveDocumentCategoryRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentCategoryController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $categories = DocumentCategory::search($request->search)
            ->withCount('documents')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('document_categories.index', compact('categories'));
    }

    public function create()
    {
        $this->authorize('create', DocumentCategory::class);

        return view('document_categories.create');
    }

    public function store(StoreDocumentCategoryRequest $request)
    {
        $this->authorize('create', DocumentCategory::class);

        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        $originalSlug = $data['slug'];
        $counter = 1;
        while (DocumentCategory::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter++;
        }

        DocumentCategory::create($data);

        $msg = !empty($data['parent_id']) ? 'Subfolder berhasil dibuat.' : 'Folder berhasil dibuat.';

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        if (!empty($data['parent_id'])) {
            return redirect()->route('documents.folder', $data['parent_id'])
                ->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    public function show(DocumentCategory $documentCategory, Request $request)
    {
        $documents = $documentCategory->documents()
            ->search($request->search)
            ->filterVisibility($request->visibilitas)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('document_categories.show', compact('documentCategory', 'documents'));
    }

    public function edit(DocumentCategory $documentCategory)
    {
        return view('document_categories.edit', compact('documentCategory'));
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $documentCategory)
    {
        $data = $request->validated();

        if ($documentCategory->name !== $data['name']) {
            $newSlug = Str::slug($data['name']);
            $originalSlug = $newSlug;
            $counter = 1;
            while (DocumentCategory::where('slug', $newSlug)->where('id', '!=', $documentCategory->id)->exists()) {
                $newSlug = $originalSlug . '-' . $counter++;
            }
            $data['slug'] = $newSlug;
        }

        $documentCategory->update($data);

        $msg = "Folder {$documentCategory->name} berhasil diperbarui.";

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        if ($documentCategory->parent_id) {
            return redirect()->route('documents.folder', $documentCategory->parent_id)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    /**
     * Move a folder to a new parent (or to root).
     * Menghitung ulang depth kategori dan seluruh turunannya dalam satu transaksi DB.
     */
    public function move(MoveDocumentCategoryRequest $request, DocumentCategory $documentCategory)
    {
        $newParentId = $request->has('parent_id')
            ? ($request->parent_id ? (int) $request->parent_id : null)
            : ($request->new_parent_id ? (int) $request->new_parent_id : null);

        $documentCategory->moveTo($newParentId);

        $destination = $newParentId
            ? DocumentCategory::find($newParentId)?->name
            : 'Root (direktori utama)';

        $msg = "Folder \"{$documentCategory->name}\" berhasil dipindahkan ke {$destination}.";

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    public function togglePin(DocumentCategory $documentCategory)
    {
        $this->authorize('update', $documentCategory);

        if (! $documentCategory->is_pinned && DocumentCategory::where('is_pinned', true)->count() >= 5) {
            return response()->json([
                'message' => 'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.'
            ], 422);
        }

        $documentCategory->update(['is_pinned' => ! $documentCategory->is_pinned]);

        return response()->json(['ok' => true]);
    }

    public function setVisibility(Request $request, DocumentCategory $documentCategory)
    {
        $this->authorize('update', $documentCategory);

        $data = $request->validate([
            'visibility' => ['required', Rule::in(array_keys(config('documents.visibility')))],
        ]);

        $documentCategory->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(DocumentCategory $documentCategory, Request $request)
    {
        $parentId = $documentCategory->parent_id;
        $name = $documentCategory->name;
        $categoryId = $documentCategory->id;

        $this->cascadeSoftDelete($documentCategory);

        $msg = "Folder \"{$name}\" beserta isinya berhasil dihapus (dapat dipulihkan).";
        $undo = [
            'url' => route('document_categories.restore', $categoryId),
            'label' => 'Urungkan',
        ];

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg)->with('undo', $undo);
        }

        if ($parentId) {
            return redirect()->route('documents.folder', $parentId)->with('success', $msg)->with('undo', $undo);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg)
            ->with('undo', $undo);
    }

    public function restore($id)
    {
        $category = DocumentCategory::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $deletedAt = $category->deleted_at;

        DB::transaction(function () use ($category, $deletedAt) {
            $category->restore();

            if ($deletedAt) {
                $descendantIds = $category->descendantIds();
                if ($descendantIds->isNotEmpty()) {
                    DocumentCategory::onlyTrashed()
                        ->whereIn('id', $descendantIds)
                        ->where('deleted_at', $deletedAt)
                        ->restore();
                }

                // Restore dokumen yang berada di folder ini atau turunannya dengan deleted_at yang sama
                $allCategoryIds = $category->descendantIds()->push($category->id);
                Document::onlyTrashed()
                    ->whereIn('document_category_id', $allCategoryIds)
                    ->where('deleted_at', $deletedAt)
                    ->chunkById(200, function ($docs) {
                        $docs->each->restore();
                    });
            }
        });

        return back()->with('success', "Folder \"{$category->name}\" berhasil dipulihkan.");
    }

    /**
     * Cascade soft-delete to children and their documents secara iteratif dalam satu transaksi.
     * Menggunakan timestamp deleted_at yang sama untuk mempermudah fitur undo/restore,
     * sambil tetap memicu event Eloquent DocumentObserver (chunkById).
     */
    private function cascadeSoftDelete(DocumentCategory $category): void
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
}
