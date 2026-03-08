<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class NinVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idType' => 'required|string|in:nin,phone',
            'idValue' => 'required|string|min:10|max:15',
            'slipType' => 'required|string|in:premium,standard,regular',
        ];
    }

    public function messages(): array
    {
        return [
            'idType.required' => 'ID type is required',
            'idType.in' => 'ID type must be either nin or phone',
            'idValue.required' => 'ID value is required',
            'slipType.required' => 'Slip type is required',
            'slipType.in' => 'Slip type must be premium, standard, or regular',
        ];
    }
}