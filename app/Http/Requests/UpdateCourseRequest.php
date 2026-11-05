<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
        $course = $this->route('course');
        $id = is_object($course) ? $course->id : $course;

        return [
            'course_code'   => ['required', 'string', 'max:20', Rule::unique('courses', 'course_code')->ignore($id)],
            'name'          => ['required', 'string', 'max:255'],
            'study_program' => ['required', Rule::in(['Informatika', 'SI', 'IT', 'Bisnis Digital'])],
            'class'         => ['required', 'string', 'max:10'],
            'semester'      => ['required', 'integer', 'min:1', 'max:8'],
            'credits'       => ['required', 'integer', 'min:1', 'max:6'],
            'type'          => ['required', Rule::in(['Wajib', 'Pilihan'])],
            'status'        => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_code' => strtoupper(trim($this->course_code)),
            'name'        => trim($this->name),
        ]);
    }
}
