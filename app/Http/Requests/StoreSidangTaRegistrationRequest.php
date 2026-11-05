<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSidangTaRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('sidang_ta_registration')
            ? 'sidang_ta_registration_update'
            : 'sidang_ta_registration_create';
        return $this->user()?->can($permission) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'draft_id' => ['required', 'uuid', 'exists:sidang_ta_registration_drafts,id'],
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:submit'],
        ];
        foreach (['requirements_version', 'student_number', 'student_name', 'study_program', 'owner_user_id',
                     'sidang_ta_period_id', 'student_phone', 'parent_phone', 'graduation_payment_date',
                     'diploma_biodata_accurate', 'academic_snapshot', 'status', 'submitted_by',
                     'reviewed_by', 'reviewed_at', 'review_notes', 'reviewed_version',
                     'source_registration_id', 'source_version', 'documents', 'file_path'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->allFiles() !== []) {
                $validator->errors()->add('documents', 'Final submit memakai berkas tersimpan, tanpa upload baru.');
            }
        });
    }
}
