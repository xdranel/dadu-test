<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentAcademicDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('bpp_payments') === null && $this->exists('bpp_payments')) $this->merge(['bpp_payments' => []]);
    }

    public function rules(): array
    {
        $rules = [
            'edit_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'edit_note' => ['nullable', 'string', 'max:1000'],
            'thesis_title_id' => ['nullable', 'string', 'max:5000'],
            'thesis_title_en' => ['nullable', 'string', 'max:5000'],
            'exam_date' => ['nullable', 'date_format:Y-m-d'],
            'exam_time' => ['nullable', 'string', 'max:20'],
            'exam_location' => ['nullable', 'string', 'max:255'],
            'ta_grade' => ['nullable', 'string', 'max:5'],
            'tak_score' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'basila_status' => ['nullable', 'in:pending,approved,rejected'],
            'retaking_courses' => ['nullable', 'string', 'max:20000'],
            'failed_courses' => ['nullable', 'string', 'max:20000'],
            'graduation_payment_status' => ['nullable', 'string', 'max:50'],
            'skpi_status' => ['nullable', 'string', 'max:80'],
            'skpi_approval' => ['nullable', 'in:approved,rejected,pending'],
            'sidang_approval_status' => ['nullable', 'string', 'max:80'],
            'bpp_payments' => ['present', 'array', 'list', 'max:40'],
            'bpp_payments.*' => ['array:period,status'],
            'bpp_payments.*.period' => ['present', 'nullable', 'string', 'regex:/\A\d{4}\/[12]\z/'],
            'bpp_payments.*.status' => ['present', 'nullable', 'string', 'max:50'],
            'bpp_current_index' => ['required', 'integer', 'min:-1', 'max:39'],
        ];
        foreach (range(1, 4) as $level) $rules['sidang_tingkat_'.$level] = ['nullable', 'in:Y,N'];
        foreach (['advisor_1', 'advisor_2', 'examiner_1', 'examiner_2'] as $field) $rules[$field] = ['nullable', 'string', 'max:255'];
        foreach (['credits_passed', 'credits_remaining'] as $field) $rules[$field] = ['nullable', 'integer', 'min:0', 'max:65535'];
        foreach (range(1, 3) as $part) $rules['skpi_status_'.$part] = ['nullable', 'in:Y,N'];
        foreach ($rules as $field => &$constraints) {
            if (in_array('nullable', $constraints, true) && $field !== 'edit_note' && !str_contains($field, '*') && !in_array('present', $constraints, true)) array_unshift($constraints, 'present');
        }
        unset($constraints);
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $rows = $this->input('bpp_payments', []);
            if (is_array($rows)) {
                $seen = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $period = $row['period'] ?? '';
                    if (!is_string($period) || (($row['status'] ?? null) === null && $period === '')) continue;
                    if (isset($seen[$period])) $validator->errors()->add('bpp_payments', 'Setiap periode BPP hanya boleh dicantumkan sekali.');
                    $seen[$period] = true;
                }
                $current = $this->input('bpp_current_index');
                if (filter_var($current, FILTER_VALIDATE_INT) !== false && (int) $current >= 0 && !array_key_exists((int) $current, $rows)) {
                    $validator->errors()->add('bpp_current_index', 'Pilih baris BPP yang tersedia atau Belum ditentukan.');
                }
            }
            $status = $this->input('skpi_status');
            $approval = $this->input('skpi_approval');
            if (($status === 'Disetujui' && $approval !== 'approved')
                || ($approval === 'approved' && $status !== 'Disetujui')
                || ($status === null && $approval !== null)) {
                $validator->errors()->add('skpi_approval', 'Status SKPI dan approval harus sesuai. Jika status belum tersedia, pilih approval Belum tersedia.');
            }
        }];
    }

    public function messages(): array
    {
        return ['*.string' => ':attribute harus berupa teks.', '*.in' => 'Pilihan :attribute tidak valid.',
            '*.numeric' => ':attribute harus berupa angka.', '*.integer' => ':attribute harus berupa bilangan bulat.',
            '*.min' => ':attribute tidak boleh kurang dari :min.', '*.max' => ':attribute melewati batas :max.',
            'exam_date.date_format' => 'Tanggal sidang harus berupa tanggal yang valid.',
            'bpp_payments.*.period.regex' => 'Gunakan format periode BPP seperti 2627/1 atau 2627/2.',
            'edit_version.required' => 'Versi data tidak tersedia. Muat ulang form.',
            'edit_version.regex' => 'Versi data tidak valid. Muat ulang form.'];
    }

    public function attributes(): array
    {
        return ['credits_passed' => 'SKS lulus', 'credits_remaining' => 'SKS belum lulus', 'tak_score' => 'Total TAK',
            'ta_grade' => 'Indeks nilai TA', 'exam_date' => 'Tanggal sidang'];
    }
}
