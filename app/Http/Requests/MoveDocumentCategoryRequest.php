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
        ];
    }

    /**
     * Validate anti-circular reference and max depth after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = $this->route('document_category') ?? $this->route('documentCategory');

            if (!$category instanceof DocumentCategory) {
                $category = DocumentCategory::findOrFail($category);
            }

            $newParentId = $this->new_parent_id;

            // Allow moving to root (null parent)
            if (is_null($newParentId)) {
                return;
            }

            // Cannot move to self
            if ($category->id == $newParentId) {
                $validator->errors()->add('new_parent_id',
                    'Folder tidak bisa dipindahkan ke dalam dirinya sendiri.');
                return;
            }

            // Anti-circular reference
            if ($category->wouldCreateCycle($newParentId)) {
                $validator->errors()->add('new_parent_id',
                    'Pemindahan ini akan membuat referensi melingkar (circular reference).');
                return;
            }

            // Max depth validation
            if ($category->wouldExceedMaxDepth($newParentId)) {
                $validator->errors()->add('new_parent_id',
                    'Pemindahan ini akan melebihi batas kedalaman maksimal folder (5 level).');
                return;
            }

            // Check unique name in new parent
            $exists = DocumentCategory::where('parent_id', $newParentId)
                ->where('name', $category->name)
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('new_parent_id',
                    "Sudah ada folder bernama \"{$category->name}\" di dalam folder tujuan.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'new_parent_id.exists' => 'Folder tujuan tidak ditemukan.',
        ];
    }
}
