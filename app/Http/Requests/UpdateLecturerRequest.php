<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLecturerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $lecturer = $this->route('lecturer');
        $id = is_object($lecturer) ? $lecturer->id : $lecturer;

        return [
            'name'              => ['required', 'string', 'max:255'],
            'lecturer_code'     => ['required', 'string', 'max:10', 'unique:lecturers,lecturer_code,' . $id],
            'active_status'     => ['nullable', 'string', 'max:50'],
            'study_program'     => ['nullable', 'string', 'max:100'],
            'nip_ypt'           => ['nullable', 'string', 'max:50'],
            'nidn'              => ['nullable', 'string', 'max:50'],
            'nuptk'             => ['nullable', 'string', 'max:50'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'front_title'       => ['nullable', 'string', 'max:50'],
            'back_title'        => ['nullable', 'string', 'max:50'],
            'jfa'               => ['nullable', 'string', 'max:100'],
            'academic_interest' => ['nullable', 'string', 'max:255'],
            'email_domain'      => ['nullable', 'email', 'max:255'],
        ];
    }
}
