<?php

namespace App\Http\Requests;

use App\Enums\AccessGroup;
use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $parentId = $this->input('parent_id');
        $parent = $parentId ? DocumentCategory::find($parentId) : null;

        if (! $user->can('create', [DocumentCategory::class, $parent])) {
            return false;
        }

        // Petugas hanya diizinkan membuat folder publik (root atau subfolder di parent publik)
        if ($user->hasRole(['Petugas', 'petugas']) && ! $user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            if ($parent) {
                $ag = $parent->access_group instanceof AccessGroup ? $parent->access_group->value : $parent->access_group;
                if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                    return false;
                }
                if ($ag === null && strtolower($parent->visibility ?? '') !== 'publik') {
                    return false;
                }
            }

            $visibility = strtolower($this->input('visibility', 'publik'));
            if ($visibility !== 'publik') {
                return false;
            }
        }

        return true;
    }

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
            'visibility'      => ['nullable', Rule::in(array_keys(config('documents.visibility')))],
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
     * Validate pinned limit and optional anti-abuse max depth ceiling.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validasi batas langit-langit keamanan kedalaman folder (opsional via config)
            $maxDepth = config('documents.max_depth');
            if ($maxDepth !== null && $this->parent_id) {
                $parent = DocumentCategory::find($this->parent_id);

                if (!$parent) {
                    $validator->errors()->add('parent_id', 'Folder induk tidak ditemukan.');
                    return;
                }

                if (($parent->depth + 1) > $maxDepth) {
                    $validator->errors()->add('parent_id', 'Batas kedalaman folder telah tercapai.');
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
