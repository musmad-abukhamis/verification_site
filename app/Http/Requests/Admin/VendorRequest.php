<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url', 'max:255'],
            'driver' => ['required', 'in:token_style_a,token_style_b,oauth'],
            'is_active' => ['boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            // Credential fields — all optional so secrets can be left unchanged
            // on edit. Which ones matter depends on the driver.
            'credentials' => ['array'],
            'credentials.key' => ['nullable', 'string', 'max:500'],
            'credentials.client_id' => ['nullable', 'string', 'max:500'],
            'credentials.client_secret' => ['nullable', 'string', 'max:500'],
            'credentials.token_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
