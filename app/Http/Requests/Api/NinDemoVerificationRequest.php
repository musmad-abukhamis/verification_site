<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class NinDemoVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstName' => 'required|string|min:2|max:100',
            'lastName' => 'required|string|min:2|max:100',
            'gender' => 'required|string|in:M,F,male,female,Male,Female',
            'dateOfBirth' => 'required|string|regex:/^\d{2}-\d{2}-\d{4}$/',
            'ref' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'firstName.required' => 'First name is required',
            'firstName.min' => 'First name must be at least 2 characters',
            'lastName.required' => 'Last name is required',
            'lastName.min' => 'Last name must be at least 2 characters',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be M, F, male, or female',
            'dateOfBirth.required' => 'Date of birth is required',
            'dateOfBirth.regex' => 'Date of birth must be in DD-MM-YYYY format',
        ];
    }
}