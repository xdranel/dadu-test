<?php

namespace App\Http\Requests;

use App\Models\Room;
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
            'name' => ['nullable', 'string', 'max:255'],
            'building' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'label' => ['nullable', 'string', Rule::in(Room::LABELS)],
            'status' => ['required', Rule::in(['Dapat Dipakai', 'Tidak Dapat Dipakai'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $roomCode = $this->input('room_code');
        if (is_string($roomCode)) {
            $this->merge(['room_code' => strtoupper(trim($roomCode))]);
        }
    }
}
