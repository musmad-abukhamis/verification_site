<?php

namespace App\Http\Requests\Nin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates a NIN verification request for any provider.
 *
 * Rules are conditional on `method`:
 *   nin         -> nin   (exactly 11 digits)
 *   phone       -> phone (exactly 11 digits)
 *   demographic -> first_name, last_name, gender, date_of_birth (YYYY-MM-DD)
 */
class ProviderVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'method' => ['required', 'string', 'in:nin,phone,demographic'],
        ];

        return match ($this->input('method')) {
            'nin' => $rules + [
                'nin' => ['required', 'string', 'regex:/^\d{11}$/'],
            ],
            'phone' => $rules + [
                'phone' => ['required', 'string', 'regex:/^\d{11}$/'],
            ],
            'demographic' => $rules + [
                'first_name'    => ['required', 'string', 'min:2', 'max:100'],
                'last_name'     => ['required', 'string', 'min:2', 'max:100'],
                'gender'        => ['required', 'string', 'in:M,F,male,female,Male,Female'],
                'date_of_birth' => ['required', 'string', 'date_format:Y-m-d'],
            ],
            default => $rules,
        };
    }

    public function messages(): array
    {
        return [
            'nin.regex'              => 'The NIN must be exactly 11 digits.',
            'phone.regex'            => 'The phone number must be exactly 11 digits.',
            'date_of_birth.date_format' => 'The date of birth must be in YYYY-MM-DD format.',
        ];
    }

    /**
     * Return validation errors as JSON in the consistent envelope.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error'   => [
                'code'    => 'validation_error',
                'message' => $validator->errors()->first(),
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }

    /**
     * Demographic payload normalized for providers.
     */
    public function demographic(): array
    {
        return [
            'first_name'    => $this->input('first_name'),
            'last_name'     => $this->input('last_name'),
            'gender'        => $this->input('gender'),
            'date_of_birth' => $this->input('date_of_birth'),
        ];
    }
}
