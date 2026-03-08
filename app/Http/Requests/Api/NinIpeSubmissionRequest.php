<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class NinIpeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'description' => 'nullable|string|max:255',
        ];

        // Provider 1 uses 'trkid', Provider 2 uses 'tracking_id'
        if ($this->has('trkid')) {
            $rules['trkid'] = 'required|string|size:15';
        } else {
            $rules['tracking_id'] = 'required|string|size:15';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'trkid.required' => 'Tracking ID is required',
            'trkid.size' => 'Tracking ID must be exactly 15 characters',
            'tracking_id.required' => 'Tracking ID is required',
            'tracking_id.size' => 'Tracking ID must be exactly 15 characters',
        ];
    }
}