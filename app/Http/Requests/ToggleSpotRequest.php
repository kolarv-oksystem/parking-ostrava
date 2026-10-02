<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleSpotRequest extends FormRequest
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
            'device_id' => ['required', 'string', 'min:10', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'spot_number' => ['required', 'integer', 'min:1', 'max:5000'],
            'vehicle_type' => ['nullable', 'string', 'in:service,private'],
            'skip_auto_release' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
