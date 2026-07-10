<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DataPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
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
        ]);
    }
}
