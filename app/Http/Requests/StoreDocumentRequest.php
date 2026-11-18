<?php

namespace App\Http\Requests;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $categoryId = $this->input('document_category_id');
        $category = $categoryId ? DocumentCategory::find($categoryId) : null;

        if (! $user->can('create', [Document::class, $category])) {
            return false;
        }

        // Petugas hanya diizinkan mengunggah dokumen pada kategori publik dengan visibilitas publik
        if ($user->hasRole(['Petugas', 'petugas']) && ! $user->hasRole(['admin', 'Admin', 'Akademik', 'superadmin', 'Superadmin'])) {
            if ($category) {
                $ag = $category->access_group instanceof AccessGroup ? $category->access_group->value : $category->access_group;
                if ($ag !== null && $ag !== AccessGroup::PUBLIK->value) {
                    return false;
                }
                if ($ag === null && strtolower($category->visibility ?? '') !== 'publik') {
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
            'document_category_id' => ['required', 'exists:document_categories,id'],
            'visibility'           => ['required', Rule::in(array_keys(config('documents.visibility')))],
            'files'                => ['required', 'array', 'min:1'],
            'files.*'              => ['file', 'max:20480',
                                       'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'],
            'judul.*'              => ['nullable', 'string', 'max:255'],
            'singkatan.*'          => ['nullable', 'string', 'max:50'],
            'deskripsi.*'          => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_category_id.required' => 'Folder / Kategori wajib dipilih.',
            'document_category_id.exists'   => 'Folder / Kategori tidak valid.',
            'visibility.required'           => 'Visibilitas wajib dipilih.',
            'visibility.in'                 => 'Visibilitas harus bernilai Publik dan Mahasiswa atau Pegawai dan Dosen.',
            'files.required'                => 'Pilih minimal satu file dokumen.',
            'files.*.max'                   => 'Ukuran file maksimal 20MB.',
            'files.*.mimes'                 => 'Format file harus berupa PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, atau RAR.',
        ];
    }
}
