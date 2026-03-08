<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNetworkRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'required|string|min:1',
            'vendor1Network' => 'required|string|max:50',
            'vendor2Network' => 'required|string|max:50',
            'vendor3Network' => 'required|string|max:50',
            'vendor4Network' => 'required|string|max:50',
            'vendor5Network' => 'required|string|max:50',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'id.required' => 'The network ID is required.',
            'vendor1Network.required' => 'Vendor 1 network ID is required.',
            'vendor2Network.required' => 'Vendor 2 network ID is required.',
            'vendor3Network.required' => 'Vendor 3 network ID is required.',
            'vendor4Network.required' => 'Vendor 4 network ID is required.',
            'vendor5Network.required' => 'Vendor 5 network ID is required.',
        ];
    }
}