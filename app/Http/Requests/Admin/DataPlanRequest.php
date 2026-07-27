<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DataPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            // The public plan id. Left blank on create it is allocated
            // automatically; settable by hand so an operator can match the ids
            // an integrator already uses with another provider.
            'code' => [
                'nullable', 'integer', 'min:1', 'max:'.Plan::MAX_CODE,
                Rule::unique('plans', 'code')->ignore($this->route('dataplan')),
            ],
            'network' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'agent_price' => ['required', 'numeric', 'min:0'],
            'api_price' => ['required', 'numeric', 'min:0'],
            'validity' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:on,off'],
            'plan_status' => ['required', 'in:on,off'],
            'mappings' => ['array'],
            'mappings.*.vendor_id' => ['required', 'string', 'exists:vendors,id'],
            'mappings.*.external_plan_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'network' => strtolower((string) $this->input('network')),
            // Blank means "allocate one"; the model does that on create.
            'code' => $this->input('code') === '' ? null : $this->input('code'),
        ]);
    }
}
