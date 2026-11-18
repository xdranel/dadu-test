<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;

class MoveDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'new_parent_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'parent_id'     => ['nullable', 'integer', 'exists:document_categories,id'],
        ];
    }

    /**
     * Validate anti-circular reference and unique name after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = $this->route('document_category') ?? $this->route('documentCategory');

            if (!$category instanceof DocumentCategory) {
                $category = DocumentCategory::findOrFail($category);
            }

            $rawParentId = $this->has('parent_id') ? $this->input('parent_id') : $this->input('new_parent_id');
            $newParentId = (!is_null($rawParentId) && $rawParentId !== '') ? (int) $rawParentId : null;

            // Allow moving to root (null parent)
            if (is_null($newParentId)) {
                return;
            }

            // Cannot move to self or descendants (anti-circular reference)
            if ($category->id == $newParentId || $category->wouldCreateCycle($newParentId)) {
                $errorField = $this->has('parent_id') ? 'parent_id' : 'new_parent_id';
                $validator->errors()->add($errorField,
                    'Folder tidak bisa dipindah ke dalam dirinya sendiri atau subfoldernya.');
                return;
            }

            // Optional anti-abuse max depth ceiling
            if (config('documents.max_depth') !== null && $category->wouldExceedMaxDepth($newParentId)) {
                $errorField = $this->has('parent_id') ? 'parent_id' : 'new_parent_id';
                $validator->errors()->add($errorField,
                    'Pemindahan ini akan melebihi batas kedalaman folder.');
                return;
            }

            // Check unique name in new parent
            $exists = DocumentCategory::where('parent_id', $newParentId)
                ->where('name', $category->name)
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {
                $errorField = $this->has('parent_id') ? 'parent_id' : 'new_parent_id';
                $validator->errors()->add($errorField,
                    "Sudah ada folder bernama \"{$category->name}\" di dalam folder tujuan.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'parent_id.exists'     => 'Folder tujuan tidak ditemukan.',
            'new_parent_id.exists' => 'Folder tujuan tidak ditemukan.',
        ];
    }
}
