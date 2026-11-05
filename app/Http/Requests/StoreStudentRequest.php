<?php

namespace App\Http\Requests;

use App\Constants\ProdiList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
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
        return [
            'student_number'      => ['required', 'string', 'max:15', 'unique:students,student_number'],
            'name'                => ['required', 'string', 'max:255'],
            'academic_year'       => ['required', 'string'],
            'study_program'       => ['required', Rule::in(\App\Constants\ProdiList::ALL)],
            'status'              => ['required', Rule::in(['Aktif', 'Cuti', 'Lulus', 'Drop Out'])],
            'email'               => ['nullable', 'email', 'unique:students,email'],
            'gender'              => ['nullable', 'string'],
            'religion'            => ['nullable', 'string'],
            'faculty'             => ['nullable', 'string'],
            'active_semester'     => ['nullable', 'integer', 'min:1', 'max:14'],
            'class_name'          => ['nullable', 'string'],
            'academic_advisor'    => ['nullable', 'string'],
            'phone_number'        => ['nullable', 'string', 'max:20'],
            'parent_phone_number' => ['nullable', 'string', 'max:20'],
            'high_school'         => ['nullable', 'string', 'max:150'],
            'passed_tpb'          => ['nullable', 'string', 'max:30'],
            'passed_tkii'         => ['nullable', 'string', 'max:30'],
            'passed_tkiii'        => ['nullable', 'string', 'max:30'],
            'passed_tkiv'         => ['nullable', 'string', 'max:30'],
        ];
    }
}
