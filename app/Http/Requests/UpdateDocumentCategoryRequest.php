<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $category = $this->route('document_category') ?? $this->route('documentCategory');
        $id = is_object($category) ? $category->id : $category;
        $existing = is_object($category) ? $category : DocumentCategory::find($id);
        $parentId = $this->has('parent_id') ? $this->parent_id : ($existing ? $existing->parent_id : null);

        return [
            'name'            => [
                'required', 'string', 'max:255',
                Rule::unique('document_categories')
                    ->where(function ($query) use ($parentId) {
                        return $parentId
                            ? $query->where('parent_id', $parentId)
                            : $query->whereNull('parent_id');
                    })
                    ->ignore($id),
            ],
            'slug'            => ['nullable', 'string', Rule::unique('document_categories', 'slug')->ignore($id)],
            'description'     => ['nullable', 'string', 'max:1000'],
            'visibility'      => ['nullable', 'in:publik,internal'],
            'is_pinned'       => ['boolean'],
            'show_when_empty' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [
            'name'            => trim((string) $this->name),
            'is_pinned'       => $this->boolean('is_pinned') || $this->has('sematkan'),
            'show_when_empty' => $this->boolean('show_when_empty'),
        ];

        if ($this->has('visibility')) {
            $mergeData['visibility'] = $this->input('visibility') ?: 'publik';
        }

        $this->merge($mergeData);
    }

    /**
     * Validate pinned limit after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_pinned')) {
                $category = $this->route('document_category') ?? $this->route('documentCategory');
                $id = is_object($category) ? $category->id : $category;

                $pinnedCount = DocumentCategory::where('is_pinned', true)
                    ->where('id', '!=', $id)
                    ->count();

                if ($pinnedCount >= 5) {
                    $validator->errors()->add('is_pinned',
                        'Maksimal 5 folder yang bisa disematkan. Lepas sematan folder lain terlebih dahulu.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.unique'   => 'Nama folder ini sudah digunakan di lokasi yang sama. Gunakan nama lain.',
            'name.required' => 'Nama folder wajib diisi.',
        ];
    }
}
