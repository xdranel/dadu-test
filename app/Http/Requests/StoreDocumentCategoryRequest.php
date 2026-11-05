<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => [
                'required', 'string', 'max:255',
                Rule::unique('document_categories')
                    ->where(function ($query) {
                        return $this->parent_id
                            ? $query->where('parent_id', $this->parent_id)
                            : $query->whereNull('parent_id');
                    }),
            ],
            'description'     => ['nullable', 'string', 'max:1000'],
            'visibility'      => ['nullable', 'in:publik,internal'],
            'parent_id'       => ['nullable', 'integer', 'exists:document_categories,id'],
            'is_pinned'       => ['boolean'],
            'show_when_empty' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'            => trim((string) $this->name),
            'visibility'      => $this->input('visibility') ?: 'publik',
            'is_pinned'       => $this->boolean('is_pinned') || $this->has('sematkan'),
            'show_when_empty' => $this->boolean('show_when_empty'),
        ]);
    }

    /**
     * Validate max depth & pinned limit after standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validasi batas kedalaman (maksimal 5 level)
            if ($this->parent_id) {
                $parent = DocumentCategory::find($this->parent_id);

                if (!$parent) {
                    $validator->errors()->add('parent_id', 'Folder induk tidak ditemukan.');
                    return;
                }

                if (!$parent->canHaveChildren()) {
                    $validator->errors()->add('parent_id',
                        'Kedalaman folder maksimal 5 level telah tercapai.');
                }
            }

            // Validasi batas maksimal 5 folder disematkan
            if ($this->boolean('is_pinned')) {
                $pinnedCount = DocumentCategory::where('is_pinned', true)->count();
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
            'name.unique'      => 'Nama folder ini sudah digunakan di lokasi yang sama. Gunakan nama lain.',
            'name.required'    => 'Nama folder wajib diisi.',
            'parent_id.exists' => 'Folder induk tidak valid.',
        ];
    }
}
