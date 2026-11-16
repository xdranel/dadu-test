<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSidangTaBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sidang_ta_registration_create');
    }

    public function rules(): array
    {
        return ['preview_token' => ['required', 'string', 'max:20000']];
    }
}
