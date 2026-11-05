<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $student = $this->route('student');
        $id = is_object($student) ? $student->id : $student;

        return [
            'student_number'      => ['required', 'string', 'max:15',
                                    Rule::unique('students', 'student_number')->ignore($id)],
            'name'                => ['required', 'string', 'max:255'],
            'gender'              => ['nullable', 'in:Laki-laki,Perempuan'],
            'religion'            => ['nullable', 'string', 'max:30'],
            'profile_photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'faculty'             => ['nullable', 'string', 'max:100'],
            'study_program'       => ['required', Rule::in(\App\Constants\ProdiList::ALL)],
            'academic_year'       => ['required', 'string'],
            'active_semester'     => ['nullable', 'integer', 'min:1', 'max:14'],
            'class_name'          => ['nullable', 'string', 'max:20'],
            'academic_advisor'    => ['nullable', 'string', 'max:255'],
            'status'              => ['required', Rule::in(['Aktif', 'Cuti', 'Lulus', 'Drop Out'])],
            'email'               => ['nullable', 'email',
                                    Rule::unique('students', 'email')->ignore($id)],
            'phone_number'        => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_number.required' => 'Student number is required.',
            'student_number.unique'   => 'This student number is already registered.',
            'name.required'           => 'Full name is required.',
            'academic_year.required'  => 'Academic year is required.',
            'study_program.in'        => 'Invalid study program.',
            'status.in'               => 'Invalid status.',
            'email.unique'            => 'This email is already in use.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'student_number' => strtoupper(trim($this->student_number)),
            'name'           => trim($this->name),
            'email'          => strtolower(trim($this->email)),
        ]);
    }
}
