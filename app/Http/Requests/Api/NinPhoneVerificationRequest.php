<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class NinPhoneVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => 'required|string|min:10|max:15|regex:/^0[0-9]{10}$/',
            'ref' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Phone number is required',
            'value.regex' => 'Phone number must be a valid Nigerian number starting with 0',
        ];
    }
}