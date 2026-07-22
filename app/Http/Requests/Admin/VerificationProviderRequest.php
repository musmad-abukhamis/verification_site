<?php

namespace App\Http\Requests\Admin;

use App\Services\Verification\AuthStyle;
use App\Services\Verification\ServiceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerificationProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->getKey();

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('verification_providers', 'slug')->ignore($providerId),
            ],
            'base_url' => ['required', 'url', 'max:255'],
            'auth_type' => ['required', Rule::in(AuthStyle::keys())],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Non-secret auth knobs (header names, body/query field names).
            'auth_config' => ['array'],
            'auth_config.header_name' => ['nullable', 'string', 'max:100'],
            'auth_config.prefix' => ['nullable', 'string', 'max:50'],
            'auth_config.key_header' => ['nullable', 'string', 'max:100'],
            'auth_config.secret_header' => ['nullable', 'string', 'max:100'],
            'auth_config.body_field' => ['nullable', 'string', 'max:100'],
            'auth_config.query_param' => ['nullable', 'string', 'max:100'],

            // All optional so a secret left blank on edit keeps its stored value.
            'credentials' => ['array'],
            'credentials.token' => ['nullable', 'string', 'max:2000'],
            'credentials.key' => ['nullable', 'string', 'max:2000'],
            'credentials.secret' => ['nullable', 'string', 'max:2000'],
            'credentials.username' => ['nullable', 'string', 'max:255'],
            'credentials.password' => ['nullable', 'string', 'max:255'],

            'extra_headers' => ['array'],
            'extra_headers.*.key' => ['nullable', 'string', 'max:100'],
            'extra_headers.*.value' => ['nullable', 'string', 'max:500'],

            'endpoints' => ['array'],
            'endpoints.*.service' => ['required', Rule::in(ServiceCatalog::keys())],
            'endpoints.*.http_method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'endpoints.*.path' => ['required', 'string', 'max:255'],
            'endpoints.*.body_type' => ['required', Rule::in(['json', 'form', 'query'])],
            'endpoints.*.is_active' => ['boolean'],

            'endpoints.*.field_map' => ['array'],
            'endpoints.*.field_map.*.input' => ['nullable', 'string', 'max:60'],
            'endpoints.*.field_map.*.field' => ['nullable', 'string', 'max:120'],
            'endpoints.*.field_map.*.format' => ['nullable', 'string', 'max:30'],
            'endpoints.*.field_map.*.transform' => ['nullable', Rule::in(['', 'upper', 'lower', 'title'])],
            'endpoints.*.field_map.*.values' => ['nullable', 'string', 'max:500'],

            'endpoints.*.static_fields' => ['array'],
            'endpoints.*.static_fields.*.key' => ['nullable', 'string', 'max:120'],
            'endpoints.*.static_fields.*.value' => ['nullable', 'string', 'max:500'],

            'endpoints.*.success_rule' => ['array'],
            'endpoints.*.success_rule.path' => ['nullable', 'string', 'max:120'],
            'endpoints.*.success_rule.in' => ['nullable', 'string', 'max:255'],
            'endpoints.*.success_rule.error_path' => ['nullable', 'string', 'max:120'],
            'endpoints.*.success_rule.data_path' => ['nullable', 'string', 'max:120'],

            'endpoints.*.response_map' => ['array'],
            'endpoints.*.response_map.*.field' => ['nullable', 'string', 'max:60'],
            'endpoints.*.response_map.*.path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, hyphens and underscores.',
            'endpoints.*.service.in' => 'Unknown service type.',
        ];
    }

    /**
     * A provider may only list a service once — the table enforces it, and a
     * duplicate would otherwise surface as a raw constraint violation.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $services = array_column($this->input('endpoints', []), 'service');
                $duplicates = array_diff_assoc($services, array_unique($services));

                foreach ($duplicates as $index => $service) {
                    $validator->errors()->add(
                        "endpoints.{$index}.service",
                        'This provider already has an endpoint for this service.',
                    );
                }
            },
        ];
    }
}
