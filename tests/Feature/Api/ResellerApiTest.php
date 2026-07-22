<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresVerificationProviders;
use Tests\TestCase;

/**
 * The reseller API: external sites calling us with an apitoken, billed to their
 * wallet at their role's rate.
 */
class ResellerApiTest extends TestCase
{
    use ConfiguresVerificationProviders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Verification has no hardcoded providers any more, so a routed chain
        // is a precondition for any lookup reaching a provider at all.
        $this->routeProviderFor(['nin.verify', 'nin.phone', 'nin.demographic', 'bvn.verify']);
    }

    private const TOKEN = 'sk_live_reseller_token';

    private function reseller(float $balance = 5000): User
    {
        return User::factory()->create([
            'role' => UserRole::API,
            'apitoken' => self::TOKEN,
            'balance' => $balance,
        ]);
    }

    private function price(string $service, float $price, string $role = ServicePrice::BASE): void
    {
        ServicePrice::forgetCache();
        ServicePrice::updateOrCreate(
            ['service' => $service, 'role' => $role],
            ['price' => $price, 'is_active' => true],
        );
        ServicePrice::forgetCache();
    }

    private function apiCall(string $method, string $uri, array $body = [])
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
            'Accept' => 'application/json',
        ])->json($method, $uri, $body);
    }

    protected function tearDown(): void
    {
        ServicePrice::forgetCache();

        parent::tearDown();
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->reseller();

        $this->json('GET', '/api/v1/balance')->assertStatus(401);
    }

    public function test_a_non_api_role_cannot_use_the_token(): void
    {
        User::factory()->create(['role' => UserRole::USER, 'apitoken' => self::TOKEN]);

        $this->apiCall('GET', '/api/v1/balance')->assertStatus(401);
    }

    public function test_it_returns_the_wallet_balance(): void
    {
        $this->reseller(2750);

        $this->apiCall('GET', '/api/v1/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', 2750)
            ->assertJsonPath('data.currency', 'NGN');
    }

    /**
     * The price list must be the caller's own rate, not the base rate -- that is
     * the whole point of giving resellers a role.
     */
    public function test_the_service_list_shows_the_callers_own_rates(): void
    {
        $this->reseller();
        $this->price('nin.verify', 100);
        $this->price('nin.verify', 40, UserRole::API->value);

        $response = $this->apiCall('GET', '/api/v1/services')->assertOk();

        $nin = collect($response->json('data.services'))->firstWhere('service', 'nin.verify');

        $this->assertSame(40, $nin['price']);
        $this->assertTrue($nin['available']);
        $this->assertSame('API', $response->json('data.role'));
    }

    public function test_a_switched_off_service_is_listed_as_unavailable(): void
    {
        $this->reseller();
        ServicePrice::where('service', 'nin.verify')->update(['is_active' => false]);
        ServicePrice::forgetCache();

        $response = $this->apiCall('GET', '/api/v1/services')->assertOk();
        $nin = collect($response->json('data.services'))->firstWhere('service', 'nin.verify');

        $this->assertFalse($nin['available']);
        $this->assertNull($nin['price']);
    }

    public function test_a_nin_verification_charges_the_api_rate(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 100);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake(['*' => Http::response(['nin' => '12345678901', 'firstname' => 'JOHN'])]);

        $this->apiCall('POST', '/api/v1/nin/verify', ['method' => 'nin', 'nin' => '12345678901'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(960.0, (float) $user->fresh()->balance);
    }

    public function test_an_invalid_nin_is_rejected_without_charging(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/verify', ['method' => 'nin', 'nin' => '123'])
            ->assertStatus(422);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    public function test_an_empty_wallet_returns_402(): void
    {
        $this->reseller(5);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/verify', ['method' => 'nin', 'nin' => '12345678901'])
            ->assertStatus(402);

        Http::assertNothingSent();
    }

    public function test_a_bvn_lookup_charges_and_returns_details(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['bvn' => '22345678901', 'lastName' => 'DOE', 'firstName' => 'JOHN'],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901', 'slip_type' => 'premium'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.surname', 'DOE');

        $this->assertSame(850.0, (float) $user->fresh()->balance);
    }

    /**
     * A lookup the provider could not fulfil must not cost the reseller money.
     */
    public function test_a_failed_bvn_lookup_is_refunded(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response(['status' => 'failed', 'message' => 'Record not found'])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901', 'slip_type' => 'premium'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'verification_failed');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_it_lists_the_available_providers(): void
    {
        $this->reseller();

        $response = $this->apiCall('GET', '/api/v1/nin/providers')->assertOk();

        // One entry now — routing, not the caller, picks the upstream provider.
        $this->assertNotEmpty($response->json('data.providers'));
        $this->assertSame('auto', $response->json('data.providers.0.key'));
        $this->assertArrayHasKey('methods', $response->json('data.providers.0'));
    }

    public function test_the_docs_are_publicly_reachable(): void
    {
        $this->get('/developers')->assertOk();
    }

    public function test_an_api_user_can_regenerate_their_token(): void
    {
        $user = $this->reseller();

        $this->actingAs($user)->post('/api-access/token')->assertSessionHasNoErrors();

        $this->assertNotSame(self::TOKEN, $user->fresh()->apitoken);
        $this->assertNotEmpty($user->fresh()->apitoken);
    }

    public function test_a_non_api_user_cannot_generate_a_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->actingAs($user)->post('/api-access/token')->assertSessionHasErrors('token');

        $this->assertEmpty($user->fresh()->apitoken);
    }

    /**
     * Granting the role has to hand over a usable credential, or the operator
     * has to chase a second step nobody documented.
     */
    public function test_granting_the_api_role_issues_a_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $user = User::factory()->create(['role' => UserRole::USER, 'apitoken' => null]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/role", ['role' => 'API'])
            ->assertSessionHasNoErrors();

        $this->assertNotEmpty($user->fresh()->apitoken);
    }
}
