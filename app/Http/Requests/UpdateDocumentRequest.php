<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_category_id' => ['sometimes', 'required', 'exists:document_categories,id'],
            'title'                => ['required', 'string', 'max:255'],
            'abbreviation'         => ['nullable', 'string', 'max:50'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'visibility'           => ['sometimes', 'required', 'in:publik,internal'],
            'file'                 => ['nullable', 'file', 'max:15360',
                                       'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_category_id.required' => 'Folder / Kategori wajib dipilih.',
            'document_category_id.exists'   => 'Folder / Kategori tidak valid.',
            'title.required'                => 'Judul dokumen wajib diisi.',
            'visibility.required'           => 'Visibilitas wajib dipilih.',
            'visibility.in'                 => 'Visibilitas harus bernilai Publik dan Mahasiswa atau Pegawai dan Dosen.',
            'file.max'                      => 'Ukuran file maksimal 15MB.',
            'file.mimes'                    => 'Format file harus berupa PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, atau RAR.',
        ];
    }
}
