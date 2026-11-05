<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_category_id' => ['required', 'exists:document_categories,id'],
            'visibility'           => ['required', 'in:publik,internal'],
            'files'                => ['required', 'array', 'min:1'],
            'files.*'              => ['file', 'max:15360',
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
            'files.*.max'                   => 'Ukuran file maksimal 15MB.',
            'files.*.mimes'                 => 'Format file harus berupa PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, atau RAR.',
        ];
    }
}
