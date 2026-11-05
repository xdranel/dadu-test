<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Http\Requests\MoveDocumentCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DocumentCategoryController extends Controller
{
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
        return view('document_categories.create');
    }

    public function store(StoreDocumentCategoryRequest $request)
    {
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

        // Redirect back to the parent folder if creating a subfolder
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
     */
    public function move(MoveDocumentCategoryRequest $request, DocumentCategory $documentCategory)
    {
        $documentCategory->update([
            'parent_id' => $request->new_parent_id,
        ]);

        // If moved to a parent, recalculate depth of children too
        $this->recalculateChildrenDepth($documentCategory);

        $destination = $request->new_parent_id
            ? DocumentCategory::find($request->new_parent_id)->name
            : 'Root (level utama)';

        $msg = "Folder \"{$documentCategory->name}\" berhasil dipindahkan ke {$destination}.";

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    public function destroy(DocumentCategory $documentCategory, Request $request)
    {
        $parentId = $documentCategory->parent_id;
        $name = $documentCategory->name;

        // Cascade soft-delete: subfolder dan dokumennya
        $this->cascadeSoftDelete($documentCategory);

        $msg = "Folder \"{$name}\" beserta isinya berhasil dihapus (dapat dipulihkan).";

        if ($request->filled('redirect_to')) {
            return redirect($request->redirect_to)->with('success', $msg);
        }

        if ($parentId) {
            return redirect()->route('documents.folder', $parentId)->with('success', $msg);
        }

        return redirect()->route('documents.index')
            ->with('success', $msg);
    }

    /**
     * Cascade soft-delete to children and their documents.
     */
    private function cascadeSoftDelete(DocumentCategory $category): void
    {
        // Soft-delete all documents in this folder
        $category->documents()->each(fn ($doc) => $doc->delete());

        // Recursively soft-delete children
        $category->children()->each(function ($child) {
            $this->cascadeSoftDelete($child);
        });

        // Finally soft-delete the folder itself
        $category->delete();
    }

    /**
     * Recalculate depth for all children after a move operation.
     */
    private function recalculateChildrenDepth(DocumentCategory $category): void
    {
        $category->children()->each(function ($child) use ($category) {
            $child->update(['depth' => $category->depth + 1]);
            $this->recalculateChildrenDepth($child);
        });
    }
}
