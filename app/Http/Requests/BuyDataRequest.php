<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BuyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Strip spaces / dashes / parens before validating length.
        $this->merge([
            'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
            'ported' => filter_var($this->input('ported', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            // The plan determines the real network server-side; the posted
            // network is only the user's tab choice, so it is optional here.
            'network' => ['nullable', 'string'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            // The server NEVER validates the phone against network prefixes —
            // the user-selected network is authoritative (ported lines / new
            // NCC prefixes make prefix checks unreliable server-side).
            'phone' => ['required', 'digits:11'],
            'ported' => ['boolean'],
            'client_ref' => ['required', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('plan_id')) {
                return;
            }

            $plan = Plan::find($this->input('plan_id'));

            // Plan must be visible and its data type switched on.
            if (! $plan || $plan->plan_status !== 'on' || $plan->status !== 'on') {
                $validator->errors()->add('plan_id', 'This plan is not available.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.digits' => 'Enter a valid 11-digit phone number.',
        ];
    }
}
