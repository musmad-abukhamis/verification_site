<?php

namespace Tests\Feature;

use App\Models\ServicePrice;
use App\Models\User;
use App\Models\VerificationAttempt;
use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use App\Services\Bvn\BvnSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BVN Search (/bvn-search) is the BVN lookup users actually reach from the
 * sidebar. It used to call ArewaSmart directly from config, which meant the
 * admin routing had no effect on it and none of its calls appeared in
 * Provider Calls. These tests pin it to the routed chain.
 */
class BvnSearchRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => 10000])->save();

        // Upsert: the base migration/seeder may already have priced this.
        ServicePrice::updateOrCreate(
            ['service' => 'bvn.search.premium', 'role' => ServicePrice::BASE],
            ['price' => 100, 'is_active' => true],
        );
        ServicePrice::forgetCache();

        return $user;
    }

    private function routeProvider(string $slug, string $baseUrl): VerificationProvider
    {
        $provider = VerificationProvider::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'base_url' => $baseUrl,
            'auth_type' => 'key_secret',
            'credentials' => ['key' => 'k-'.$slug, 'secret' => 's-'.$slug],
            'is_active' => true,
            'priority' => 10,
            'timeout_seconds' => 30,
        ]);

        VerificationEndpoint::create([
            'provider_id' => $provider->getKey(),
            'service' => 'bvn.verify',
            'http_method' => 'POST',
            'path' => '/bvn/enhanced',
            'body_type' => 'json',
            'field_map' => ['bvn' => 'bvn'],
            'is_active' => true,
        ]);

        VerificationRoute::create([
            'service' => 'bvn.verify',
            'provider_id' => $provider->getKey(),
            'position' => 1,
        ]);

        return $provider;
    }

    public function test_a_bvn_search_uses_the_routed_provider_and_is_logged(): void
    {
        $user = $this->user();
        $this->routeProvider('payvessel', 'https://api.payvessel.com');

        Http::fake([
            'api.payvessel.com/*' => Http::response([
                'success' => true,
                'data' => [
                    'bvn' => '12345678901',
                    'first_name' => 'ADA',
                    'middle_name' => 'NGOZI',
                    'last_name' => 'OKAFOR',
                    'gender' => 'FEMALE',
                    'birthday' => '1990-04-02',
                    'phone_number' => '08012345678',
                ],
            ], 200),
            // Must never be touched now that a provider is routed.
            'api.arewasmart.com.ng/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);

        $result = app(BvnSearchService::class)->search($user, '12345678901', 'premium');

        $this->assertTrue($result['success']);
        $this->assertSame('OKAFOR', $result['data']['surname']);
        $this->assertSame('ADA', $result['data']['firstname']);
        $this->assertSame('1990-04-02', $result['data']['dob']);
        $this->assertSame('08012345678', $result['data']['phone']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.payvessel.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'arewasmart'));

        // The reported symptom: the call must appear in Provider Calls.
        $attempt = VerificationAttempt::where('service', 'bvn.verify')->firstOrFail();
        $this->assertSame('Payvessel', $attempt->provider_name);
        $this->assertSame('success', $attempt->outcome);
        $this->assertSame($user->id, $attempt->user_id);
    }

    public function test_it_fails_over_and_logs_both_hops(): void
    {
        $user = $this->user();
        $this->routeProvider('payvessel', 'https://api.payvessel.com');

        $backup = VerificationProvider::create([
            'name' => 'Prembly', 'slug' => 'prembly', 'base_url' => 'https://api.prembly.com',
            'auth_type' => 'header_key', 'auth_config' => ['header_name' => 'x-api-key'],
            'credentials' => ['token' => 't'], 'is_active' => true, 'priority' => 20, 'timeout_seconds' => 30,
        ]);
        VerificationEndpoint::create([
            'provider_id' => $backup->getKey(), 'service' => 'bvn.verify', 'http_method' => 'POST',
            'path' => '/verification/bvn_validation', 'body_type' => 'json',
            'field_map' => ['bvn' => 'number'], 'is_active' => true,
        ]);
        VerificationRoute::create(['service' => 'bvn.verify', 'provider_id' => $backup->getKey(), 'position' => 2]);

        Http::fake([
            'api.payvessel.com/*' => Http::response(['success' => false, 'message' => 'BVN not found'], 200),
            'api.prembly.com/*' => Http::response(['status' => true, 'data' => ['bvn' => '1', 'surname' => 'OKAFOR']], 200),
        ]);

        $result = app(BvnSearchService::class)->search($user, '12345678901', 'premium');

        $this->assertTrue($result['success']);
        $this->assertSame('OKAFOR', $result['data']['surname']);
        $this->assertSame(
            ['fail', 'success'],
            VerificationAttempt::orderBy('id')->pluck('outcome')->all(),
        );
    }

    public function test_the_user_is_refunded_when_every_routed_provider_declines(): void
    {
        $user = $this->user();
        $this->routeProvider('payvessel', 'https://api.payvessel.com');

        Http::fake(['api.payvessel.com/*' => Http::response(['success' => false, 'message' => 'BVN does not exist'], 200)]);

        $result = app(BvnSearchService::class)->search($user, '12345678901', 'premium');

        $this->assertFalse($result['success']);
        $this->assertSame('BVN does not exist', $result['message']);
        $this->assertSame(10000.0, (float) $user->fresh()->balance, 'the charge must be refunded');
    }

    public function test_it_refuses_and_refunds_when_nothing_is_routed(): void
    {
        // No silent fallback to a config-file provider: an unrouted service must
        // refuse rather than quietly call a provider the admin never chose.
        $user = $this->user();

        Http::fake();

        $result = app(BvnSearchService::class)->search($user, '12345678901', 'premium');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No verification provider', $result['message']);
        $this->assertSame(10000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }
}
