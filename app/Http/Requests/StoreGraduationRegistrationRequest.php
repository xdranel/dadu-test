<?php

namespace App\Http\Requests;

use App\Models\GraduationRegistration;
use Illuminate\Foundation\Http\FormRequest;

class StoreGraduationRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSubmit = $this->input('action') === 'submit';
        $isUpdating = $this->method() === 'PUT' || $this->method() === 'PATCH';

        $rules = [
            'graduation_period'        => [$isSubmit ? 'required' : 'nullable', 'string', 'max:100'],
            
            // Input manual mahasiswa (tetap editable)
            'student_phone'            => [$isSubmit ? 'required' : 'nullable', 'string', 'max:20'],
            'parent_phone'             => [$isSubmit ? 'required' : 'nullable', 'string', 'max:20'],
            'personal_email'           => [$isSubmit ? 'required' : 'nullable', 'email', 'max:255', 'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/i'],
            'revision_completion_date' => [$isSubmit ? 'required' : 'nullable', 'date'],

            // Conditional title change
            'has_title_change'          => ['nullable', 'in:ya,tidak'],
            'sk_update_status'          => ['nullable', 'string', 'in:not_processed_laa,processed_laa'],
            'sk_update_submission_date' => ['nullable', 'date'],

            'revision_deadline'        => [$isSubmit ? 'required' : 'nullable', 'date'],

            'biodata_correction_note'  => ['nullable', 'string', 'max:2000'],

            // Honors Predicate
            'honors_predicate'            => ['nullable', 'string', 'in:none,cumlaude,summa_cumlaude'],
            'predicate_criteria'          => ['nullable', 'string', 'in:hki,lomba,publikasi'],
            'predicate_umkm_partner'      => ['nullable', 'string', 'max:255'],
            'predicate_competition_level' => ['nullable', 'string', 'max:100'],
            'predicate_journal_index'     => ['nullable', 'string', 'max:100'],
            'predicate_document'          => ['nullable', 'file', 'max:102400'],
        ];

        // Document upload rules
        $documentTypes = array_keys(GraduationRegistration::DOCUMENT_TYPES);
        foreach ($documentTypes as $type) {
            $rules["documents.{$type}"] = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'graduation_period.required'      => 'Periode yudisium wajib diisi saat mengajukan.',
            'student_phone.required'          => 'Nomor HP mahasiswa wajib diisi saat mengajukan.',
            'parent_phone.required'           => 'Nomor HP orang tua wajib diisi saat mengajukan.',
            'personal_email.required'         => 'Email pribadi (Gmail) wajib diisi saat mengajukan.',
            'personal_email.email'            => 'Format email pribadi tidak valid.',
            'personal_email.regex'            => 'Email pribadi harus menggunakan domain Gmail (@gmail.com).',
            'revision_completion_date.required' => 'Tanggal selesai revisi wajib diisi saat mengajukan.',
            'revision_deadline.required'        => 'Batas revisi wajib diisi saat mengajukan.',
            'has_title_change.in'             => 'Pilihan perubahan judul harus "ya" atau "tidak".',
            'documents.*.max'                 => 'Ukuran file maksimal 10MB.',
            'documents.*.mimes'               => 'Format file harus PDF, JPG, atau PNG.',
        ];
    }
}
