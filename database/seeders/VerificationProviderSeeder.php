<?php

namespace Database\Seeders;

use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationSetting;
use Illuminate\Database\Seeder;

/**
 * Ready-made provider definitions for the Nigerian NIN/BVN providers whose API
 * shapes are documented.
 *
 * Every one is seeded INACTIVE with empty credentials — they are templates.
 * Paste the key into Admin > Verification > Providers, use the per-service
 * "Test" button to confirm the field map against a real record, then activate
 * and give it a routing position.
 *
 * Re-running is safe: providers are matched on `slug`, and existing rows keep
 * their credentials and active flag. Only the endpoint definitions are refreshed,
 * so a corrected field map ships without wiping the key.
 */
class VerificationProviderSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->providers() as $definition) {
            $endpoints = $definition['endpoints'];
            unset($definition['endpoints']);

            $provider = VerificationProvider::firstOrNew(['slug' => $definition['slug']]);

            // Never clobber a live provider's credentials or on/off state.
            $provider->fill($provider->exists
                ? ['name' => $definition['name'], 'base_url' => $definition['base_url']]
                : $definition + ['is_active' => false, 'credentials' => []]);

            $provider->save();

            foreach ($endpoints as $endpoint) {
                VerificationEndpoint::updateOrCreate(
                    ['provider_id' => $provider->getKey(), 'service' => $endpoint['service']],
                    $endpoint,
                );
            }
        }

        // Failover on by default: the whole point of a chain is that one
        // provider being down is invisible to the customer.
        foreach (['failover_enabled' => true, 'failover_max_attempts' => 0, 'attempt_retention_days' => 30] as $key => $value) {
            if (VerificationSetting::get($key) === null) {
                VerificationSetting::put($key, $value);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providers(): array
    {
        return [
            // PayVessel — key + secret in two headers, canonical field names.
            [
                'name' => 'PayVessel KYC',
                'slug' => 'payvessel',
                'base_url' => 'https://api.payvessel.com',
                'auth_type' => 'key_secret',
                'auth_config' => ['key_header' => 'api-key', 'secret_header' => 'api-secret'],
                'extra_headers' => ['Content-Type' => 'application/json'],
                'timeout_seconds' => 40,
                'priority' => 20,
                'notes' => 'Enhanced BVN/NIN. Responses carry a `charges` block; `success` is the status field.',
                'endpoints' => [
                    [
                        'service' => 'bvn.verify',
                        'http_method' => 'POST',
                        'path' => '/kyc/api/v1/merchant/bvn/enhanced',
                        'body_type' => 'json',
                        'field_map' => ['bvn' => 'bvn'],
                        'success_rule' => ['path' => 'success', 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'nin.verify',
                        'http_method' => 'POST',
                        'path' => '/kyc/api/v1/merchant/nin/enhanced',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'nin'],
                        // `error_message` is populated on a miss even when the
                        // HTTP status is 200.
                        'success_rule' => ['path' => 'success', 'data_path' => 'data', 'error_path' => 'data.error_message'],
                    ],
                ],
            ],

            // Prembly — x-api-key header, everything is called `number`.
            [
                'name' => 'Prembly',
                'slug' => 'prembly',
                'base_url' => 'https://api.prembly.com',
                'auth_type' => 'header_key',
                'auth_config' => ['header_name' => 'x-api-key'],
                'extra_headers' => ['accept' => 'application/json'],
                'timeout_seconds' => 40,
                'priority' => 30,
                'notes' => 'Identitypass. Every identifier is submitted as `number`.',
                'endpoints' => [
                    [
                        'service' => 'bvn.verify',
                        'http_method' => 'POST',
                        'path' => '/verification/bvn_validation',
                        'body_type' => 'json',
                        'field_map' => ['bvn' => 'number'],
                        'success_rule' => ['path' => 'status', 'error_path' => 'error'],
                    ],
                    [
                        'service' => 'nin.verify',
                        'http_method' => 'POST',
                        'path' => '/verification/vnin-basic',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'number'],
                        'success_rule' => ['path' => 'status', 'error_path' => 'error'],
                    ],
                ],
            ],

            // IDTRA — bearer token, also `number` for both NIN and phone.
            [
                'name' => 'IDTRA',
                'slug' => 'idtra',
                'base_url' => 'https://idtra.com/api',
                'auth_type' => 'bearer',
                'timeout_seconds' => 40,
                'priority' => 40,
                'notes' => 'Phone lookup returns the full NIMC demographic record.',
                'endpoints' => [
                    [
                        'service' => 'nin.verify',
                        'http_method' => 'POST',
                        'path' => '/advanced_nin/verify',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'number'],
                        'success_rule' => ['path' => 'status', 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'nin.phone',
                        'http_method' => 'POST',
                        'path' => '/phone/verify',
                        'body_type' => 'json',
                        'field_map' => ['phone' => 'number'],
                        'success_rule' => ['path' => 'status', 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'account.resolve',
                        'http_method' => 'POST',
                        'path' => '/account/',
                        'body_type' => 'json',
                        'field_map' => ['account_number' => 'account_number', 'bank_code' => 'bank_code'],
                        'success_rule' => ['path' => 'status'],
                    ],
                ],
            ],

            // ArewaSmart — bearer token; demographic wants dd-mm-yyyy and a
            // single-letter gender, which is exactly what the field map is for.
            [
                'name' => 'ArewaSmart',
                'slug' => 'arewasmart',
                'base_url' => 'https://api.arewasmart.com.ng/api/v1',
                'auth_type' => 'bearer',
                'extra_headers' => ['Accept' => 'application/json'],
                'timeout_seconds' => 45,
                'priority' => 10,
                'notes' => 'Codes: 111111 success, 222222 not found (still charged), 333333/444444 free errors.',
                'endpoints' => [
                    [
                        'service' => 'bvn.verify',
                        'http_method' => 'POST',
                        'path' => '/bvn/verify',
                        'body_type' => 'json',
                        'field_map' => ['bvn' => 'bvn'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '111111'], 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'nin.verify',
                        'http_method' => 'POST',
                        'path' => '/nin/verify',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'nin'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '111111'], 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'nin.phone',
                        'http_method' => 'POST',
                        'path' => '/nin/phone',
                        'body_type' => 'json',
                        'field_map' => ['phone' => 'value'],
                        'static_fields' => ['ref' => '{reference}'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '111111'], 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'nin.demographic',
                        'http_method' => 'POST',
                        'path' => '/nin/demo',
                        'body_type' => 'json',
                        'field_map' => [
                            'first_name' => 'firstName',
                            'last_name' => 'lastName',
                            'middle_name' => false, // not accepted by this endpoint
                            'gender' => ['field' => 'gender', 'values' => ['male' => 'M', 'female' => 'F'], 'transform' => 'upper'],
                            'date_of_birth' => ['field' => 'dateOfBirth', 'format' => 'd-m-Y'],
                        ],
                        'static_fields' => ['ref' => '{reference}'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '111111'], 'data_path' => 'data'],
                    ],
                    [
                        'service' => 'bvn.retrieval.phone',
                        'http_method' => 'POST',
                        'path' => '/bvn/phone-search',
                        'body_type' => 'json',
                        'field_map' => ['phone' => 'phone_number', 'field_code' => 'field_code'],
                        'success_rule' => ['path' => 'success', 'data_path' => 'data'],
                    ],
                ],
            ],

            // TechHub — no auth header at all; the key rides in the body.
            [
                'name' => 'TechHub',
                'slug' => 'techhub',
                'base_url' => 'https://techhubltd.co/api/verification',
                'auth_type' => 'body_key',
                'auth_config' => ['body_field' => 'api_key'],
                'timeout_seconds' => 45,
                'priority' => 50,
                'notes' => 'PHP-script endpoints (.php paths). IPE clearance returns `user_data`, not `data`.',
                'endpoints' => [
                    [
                        'service' => 'nin.verify',
                        'http_method' => 'POST',
                        'path' => '/nin_by_nin.php',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'nin'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '00']],
                    ],
                    [
                        'service' => 'nin.ipe',
                        'http_method' => 'POST',
                        'path' => '/ipe_clearance.php',
                        'body_type' => 'json',
                        'field_map' => ['nin' => 'nin', 'tracking_id' => 'trackingID'],
                        'success_rule' => ['path' => 'status', 'in' => ['success', 'true', '00'], 'data_path' => 'user_data'],
                    ],
                ],
            ],
        ];
    }
}
