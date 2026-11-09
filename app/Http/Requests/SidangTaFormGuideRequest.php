<?php

namespace App\Http\Requests;

use App\Support\SidangTaFormGuideSlots;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SidangTaFormGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->method()) {
            'POST' => 'sidang_ta_form_guide_create',
            'PUT', 'PATCH' => 'sidang_ta_form_guide_update',
            default => null,
        };

        return $permission !== null && ($this->user()?->can($permission) ?? false);
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', Rule::in(array_keys(SidangTaFormGuideSlots::labels()))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'link_label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
