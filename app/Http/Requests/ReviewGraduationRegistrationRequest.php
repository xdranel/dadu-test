<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewGraduationRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'       => ['required_without:action', 'nullable', 'string', 'in:approved,needs_review,rejected,approve,reject'],
            'action'       => ['required_without:status', 'nullable', 'string', 'in:approve,reject,needs_review,approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'is_free_of_sanction' => ['nullable', 'boolean'],
            'cumlaude_verified_by_admin' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'    => 'Status review tidak valid.',
            'action.in'    => 'Aksi review tidak valid.',
        ];
    }
}
