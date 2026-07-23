<?php

namespace App\Http\Requests\Nin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates a NIN verification request for any provider.
 *
 * Rules are conditional on the lookup method:
 *   nin         -> nin   (exactly 11 digits)
 *   phone       -> phone (exactly 11 digits)
 *   demographic -> first_name, last_name, gender, date_of_birth (YYYY-MM-DD)
 *
 * Callers do not send the method: it follows from the identifier they supply,
 * which is the only thing they actually have. Asking an integrator to name it
 * as well just adds a field they can contradict -- `method: phone` with a `nin`
 * in the body has no sensible reading. The verification screens still send it
 * explicitly, and an explicit value is always honoured.
 */
class ProviderVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Fill in the method from whichever identifier was sent.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('method')) {
            return;
        }

        $this->merge(['method' => $this->inferMethod()]);
    }

    /**
     * NIN first, then phone: if a caller sends both, the NIN is the stronger
     * identifier and the one they are asking us about.
     */
    private function inferMethod(): ?string
    {
        return match (true) {
            $this->filled('nin') => 'nin',
            $this->filled('phone') => 'phone',
            $this->filled('first_name') || $this->filled('last_name') => 'demographic',
            default => null,
        };
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
            // Reached when nothing identifiable was sent, so the message names
            // the choices rather than a `method` field the caller never sees.
            'method.required' => 'Send a nin, a phone number, or first_name, last_name, gender and date_of_birth.',
            'method.in' => 'Send a nin, a phone number, or first_name, last_name, gender and date_of_birth.',
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
