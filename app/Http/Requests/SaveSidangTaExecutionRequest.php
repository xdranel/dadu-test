<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sidang_ta_period_update');
    }

    public static function windowRules(): array
    {
        return [
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'allowed_weekdays' => ['required', 'array', 'min:1', 'max:7'], 'allowed_weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'break_start_time' => ['nullable', 'required_with:break_end_time', 'date_format:H:i'], 'break_end_time' => ['nullable', 'required_with:break_start_time', 'date_format:H:i', 'after:break_start_time'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:900']];
    }

    public function rules(): array
    {
        return self::windowRules() + ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
