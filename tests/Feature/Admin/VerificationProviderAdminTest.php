<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The admin screens are the whole point of the engine: a provider must be
 * addable, testable and routable from the browser, and its secrets must never
 * travel back to it.
 */
class VerificationProviderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Prembly',
            'slug' => 'prembly',
            'base_url' => 'https://api.prembly.com',
            'auth_type' => 'header_key',
            'auth_config' => ['header_name' => 'x-api-key'],
            'credentials' => ['token' => 'live_sk_supersecret'],
            'extra_headers' => [['key' => 'accept', 'value' => 'application/json']],
            'timeout_seconds' => 30,
            'priority' => 10,
            'is_active' => true,
            'endpoints' => [[
                'service' => 'bvn.verify',
                'http_method' => 'POST',
                'path' => '/verification/bvn_validation',
                'body_type' => 'json',
                'is_active' => true,
                'field_map' => [['input' => 'bvn', 'field' => 'number', 'format' => '', 'transform' => '', 'values' => '']],
                'static_fields' => [],
                'success_rule' => ['path' => 'status', 'in' => 'success, true', 'error_path' => 'error', 'data_path' => ''],
                'response_map' => [],
            ]],
        ], $overrides);
    }

    public function test_an_admin_can_add_a_provider_with_a_service_endpoint(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/verification-providers', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $provider = VerificationProvider::where('slug', 'prembly')->firstOrFail();

        $this->assertSame('header_key', $provider->auth_type);
        $this->assertSame('live_sk_supersecret', $provider->credentials['token']);
        $this->assertSame(['accept' => 'application/json'], $provider->extra_headers);

        $endpoint = $provider->endpoints()->firstOrFail();
        $this->assertSame('bvn.verify', $endpoint->service);
        // A row with only a field name collapses to the string shorthand.
        $this->assertSame(['bvn' => 'number'], $endpoint->field_map);
        $this->assertSame(['success', 'true'], $endpoint->success_rule['in']);
    }

    public function test_a_field_map_with_a_date_format_keeps_the_object_form(): void
    {
        $payload = $this->payload();
        $payload['endpoints'][0]['service'] = 'nin.demographic';
        $payload['endpoints'][0]['field_map'] = [
            ['input' => 'date_of_birth', 'field' => 'dateOfBirth', 'format' => 'd-m-Y', 'transform' => '', 'values' => ''],
            ['input' => 'gender', 'field' => 'gender', 'format' => '', 'transform' => 'upper', 'values' => 'male=M, female=F'],
        ];

        $this->actingAs($this->admin())
            ->post('/admin/verification-providers', $payload)
            ->assertSessionHasNoErrors();

        $map = VerificationProvider::where('slug', 'prembly')->firstOrFail()->endpoints()->firstOrFail()->field_map;

        $this->assertSame(['field' => 'dateOfBirth', 'format' => 'd-m-Y'], $map['date_of_birth']);
        $this->assertSame(['male' => 'M', 'female' => 'F'], $map['gender']['values']);
    }

    public function test_secrets_are_never_sent_to_the_browser(): void
    {
        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload());

        $response = $this->actingAs($this->admin())->get('/admin/verification-providers');

        $response->assertOk();
        $response->assertDontSee('live_sk_supersecret');
        // Only a set/not-set flag reaches the form.
        $response->assertSee('credential_status');
    }

    public function test_a_blank_credential_on_edit_keeps_the_stored_secret(): void
    {
        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload());
        $provider = VerificationProvider::where('slug', 'prembly')->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/verification-providers/{$provider->id}", $this->payload([
                'name' => 'Prembly (renamed)',
                'credentials' => ['token' => ''],
            ]))
            ->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertSame('Prembly (renamed)', $provider->name);
        $this->assertSame('live_sk_supersecret', $provider->credentials['token']);
    }

    public function test_a_provider_cannot_list_the_same_service_twice(): void
    {
        $payload = $this->payload();
        $payload['endpoints'][] = $payload['endpoints'][0];

        $this->actingAs($this->admin())
            ->post('/admin/verification-providers', $payload)
            ->assertSessionHasErrors('endpoints.1.service');
    }

    public function test_removing_an_endpoint_deletes_it(): void
    {
        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload());
        $provider = VerificationProvider::where('slug', 'prembly')->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/verification-providers/{$provider->id}", $this->payload(['endpoints' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $provider->endpoints()->count());
    }

    public function test_the_test_button_calls_the_provider_and_returns_a_normalized_result(): void
    {
        Http::fake(['api.prembly.com/*' => Http::response([
            'status' => true,
            'data' => ['bvn' => '12345678901', 'firstName' => 'ADA', 'surname' => 'OKAFOR'],
        ], 200)]);

        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload());
        $provider = VerificationProvider::where('slug', 'prembly')->firstOrFail();

        $response = $this->actingAs($this->admin())->post(
            "/admin/verification-providers/{$provider->id}/test",
            ['service' => 'bvn.verify', 'input' => ['bvn' => '12345678901']],
        );

        $response->assertSessionHas('testResult');
        $result = session('testResult');

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('ADA', $result['normalized']['first_name']);
        $this->assertSame('OKAFOR', $result['normalized']['last_name']);
        // The echoed request must not leak the API key.
        $this->assertStringNotContainsString('live_sk_supersecret', json_encode($result['request']));
    }

    public function test_an_admin_can_order_the_failover_chain(): void
    {
        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload());
        $this->actingAs($this->admin())->post('/admin/verification-providers', $this->payload([
            'name' => 'PayVessel', 'slug' => 'payvessel', 'base_url' => 'https://api.payvessel.com',
        ]));

        $ids = VerificationProvider::orderBy('name')->pluck('id', 'name');

        $this->actingAs($this->admin())
            ->put('/admin/verification-routing', [
                'routes' => [[
                    'service' => 'bvn.verify',
                    'provider_ids' => [$ids['PayVessel'], $ids['Prembly']],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $chain = VerificationRoute::forService('bvn.verify')->get();

        $this->assertSame([$ids['PayVessel'], $ids['Prembly']], $chain->pluck('provider_id')->all());
        $this->assertSame([1, 2], $chain->pluck('position')->all());
    }

    public function test_a_non_admin_cannot_reach_the_provider_screens(): void
    {
        // AdminMiddleware bounces browser requests to the dashboard rather than
        // rendering a 403; only JSON callers get the status code.
        $this->actingAs(User::factory()->create(['role' => UserRole::USER]))
            ->get('/admin/verification-providers')
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, VerificationProvider::count());
    }
}
