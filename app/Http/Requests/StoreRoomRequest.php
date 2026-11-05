<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
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
            'room_code' => ['required', 'string', 'max:20', 'unique:rooms,room_code'],
            'name'      => ['required', 'string', 'max:255'],
            'building'  => ['required', 'string', 'max:100'],
            'capacity'  => ['required', 'integer', 'min:1', 'max:1000'],
            'status'    => ['required', Rule::in(['Dapat Dipakai', 'Tidak Dapat Dipakai'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_code' => strtoupper(trim($this->room_code)),
            'name'      => trim($this->name),
        ]);
    }
}
