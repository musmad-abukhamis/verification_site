<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorApiRequest extends FormRequest
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
            'id' => 'nullable|integer',
            'vendor1url' => 'nullable|url|max:255',
            'vendor1key' => 'nullable|string|max:255',
            'vendor2url' => 'nullable|url|max:255',
            'vendor2key' => 'nullable|string|max:255',
            'vendor3url' => 'nullable|url|max:255',
            'vendor3key' => 'nullable|string|max:255',
            'vendor4url' => 'nullable|url|max:255',
            'vendor4key' => 'nullable|string|max:255',
            'vendor5url' => 'nullable|url|max:255',
            'vendor5key' => 'nullable|string|max:255',
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
            'vendor1url.url' => 'The vendor 1 URL must be a valid URL.',
            'vendor1url.max' => 'The vendor 1 URL may not be greater than 255 characters.',
            'vendor2url.url' => 'The vendor 2 URL must be a valid URL.',
            'vendor2url.max' => 'The vendor 2 URL may not be greater than 255 characters.',
            'vendor3url.url' => 'The vendor 3 URL must be a valid URL.',
            'vendor3url.max' => 'The vendor 3 URL may not be greater than 255 characters.',
            'vendor4url.url' => 'The vendor 4 URL must be a valid URL.',
            'vendor4url.max' => 'The vendor 4 URL may not be greater than 255 characters.',
            'vendor5url.url' => 'The vendor 5 URL must be a valid URL.',
            'vendor5url.max' => 'The vendor 5 URL may not be greater than 255 characters.',
            'vendor1key.max' => 'The vendor 1 key may not be greater than 255 characters.',
            'vendor2key.max' => 'The vendor 2 key may not be greater than 255 characters.',
            'vendor3key.max' => 'The vendor 3 key may not be greater than 255 characters.',
            'vendor4key.max' => 'The vendor 4 key may not be greater than 255 characters.',
            'vendor5key.max' => 'The vendor 5 key may not be greater than 255 characters.',
        ];
    }
}