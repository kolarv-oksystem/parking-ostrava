<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitParkingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'capacity_total' => ['required', 'integer', 'min:1', 'max:5000'],
            'free_spots' => ['nullable', 'integer', 'min:0'],
            'manual_password' => ['required', 'string', 'min:4', 'max:255'],
        ];
    }
}
