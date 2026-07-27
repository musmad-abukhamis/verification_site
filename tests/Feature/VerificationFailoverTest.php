<?php

namespace Tests\Feature;

use App\Models\VerificationAttempt;
use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use App\Models\VerificationSetting;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerificationFailoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        VerificationSetting::put('failover_enabled', true);
        VerificationSetting::put('failover_max_attempts', 0);
    }

    /**
     * Two providers on the same service, routed in the given order.
     *
     * @return array{0: VerificationProvider, 1: VerificationProvider}
     */
    private function twoProviders(string $service = 'bvn.verify'): array
    {
        $providers = [];

        foreach ([['primary', 'https://primary.test'], ['backup', 'https://backup.test']] as $i => [$slug, $url]) {
            $provider = VerificationProvider::create([
                'name' => ucfirst($slug),
                'slug' => $slug,
                'base_url' => $url,
                'auth_type' => 'bearer',
                'credentials' => ['token' => 'secret-token-'.$slug],
                'is_active' => true,
                'priority' => ($i + 1) * 10,
                'timeout_seconds' => 30,
            ]);

            VerificationEndpoint::create([
                'provider_id' => $provider->getKey(),
                'service' => $service,
                'http_method' => 'POST',
                'path' => '/verify',
                'body_type' => 'json',
                'field_map' => ['bvn' => 'number', 'phone' => 'number'],
                'is_active' => true,
            ]);

            VerificationRoute::create([
                'service' => $service,
                'provider_id' => $provider->getKey(),
                'position' => $i + 1,
            ]);

            $providers[] = $provider;
        }

        return $providers;
    }

    public function test_it_fails_over_to_the_next_provider_when_the_primary_declines(): void
    {
        $this->twoProviders();

        Http::fake([
            'primary.test/*' => Http::response(['status' => false, 'message' => 'BVN does not exist'], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '12345678901', 'surname' => 'DOE']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        $this->assertTrue($outcome->isSuccess());
        $this->assertSame('Backup', $outcome->providerName);
        $this->assertSame('DOE', $outcome->data['last_name']);
        $this->assertCount(2, $outcome->attempts, 'both hops should be traced');
    }

    public function test_it_stops_at_the_primary_when_failover_is_disabled(): void
    {
        $this->twoProviders();
        VerificationSetting::put('failover_enabled', false);

        Http::fake([
            'primary.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        $this->assertFalse($outcome->isSuccess());
        $this->assertCount(1, $outcome->attempts);
    }

    public function test_a_lookup_fails_over_after_an_ambiguous_reply(): void
    {
        // bvn.verify is idempotent — a 500 from the primary is safe to retry
        // elsewhere, because the worst case is being billed twice for a query.
        $this->twoProviders();

        Http::fake([
            'primary.test/*' => Http::response('gateway error', 502),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1', 'surname' => 'OK']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        $this->assertTrue($outcome->isSuccess());
        $this->assertSame('Backup', $outcome->providerName);
    }

    public function test_a_submission_service_never_fails_over_after_an_ambiguous_reply(): void
    {
        // bvn.retrieval.phone creates an upstream ticket. A timeout may mean the
        // request landed, so re-sending it to a second provider would duplicate
        // a real submission — the chain must stop.
        $this->twoProviders('bvn.retrieval.phone');

        Http::fake([
            'primary.test/*' => Http::response('gateway timeout', 504),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['reference' => 'X']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.retrieval.phone', ['phone' => '08012345678']);

        $this->assertTrue($outcome->isTimeout());
        $this->assertCount(1, $outcome->attempts, 'the backup must not be tried');
    }

    public function test_it_skips_a_provider_with_no_credentials(): void
    {
        [$primary] = $this->twoProviders();
        $primary->update(['credentials' => []]);

        Http::fake([
            'primary.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1', 'surname' => 'OK']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        $this->assertSame('Backup', $outcome->providerName);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'primary.test'));
    }

    public function test_it_refuses_when_no_provider_serves_the_service(): void
    {
        $outcome = app(VerificationDispatcher::class)->verify('nin.verify', ['nin' => '12345678901']);

        $this->assertTrue($outcome->isFail());
        $this->assertStringContainsString('No verification provider', $outcome->message);
    }

    public function test_it_maps_canonical_inputs_onto_each_providers_field_names(): void
    {
        $this->twoProviders();

        Http::fake(['primary.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200)]);

        app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        Http::assertSent(fn ($request) => $request['number'] === '12345678901' && ! isset($request['bvn']));
    }

    public function test_the_attempt_log_records_every_hop_without_credentials(): void
    {
        $this->twoProviders();

        Http::fake([
            'primary.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
        ]);

        app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901'], [
            'reference' => 'REF-1',
        ]);

        $attempts = VerificationAttempt::orderBy('id')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame(['fail', 'success'], $attempts->pluck('outcome')->all());
        $this->assertSame('REF-1', $attempts->first()->reference);

        // The bearer token must appear nowhere in the stored payload: header
        // values are dropped entirely and any credential echoed elsewhere is
        // masked.
        $encoded = json_encode($attempts->pluck('request_payload'));
        $this->assertStringNotContainsString('secret-token', $encoded);
    }

    public function test_it_falls_back_to_priority_order_when_a_service_has_no_routes(): void
    {
        $this->twoProviders();
        VerificationRoute::query()->delete();

        Http::fake([
            'primary.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        // Primary has the lower `priority` value, so it goes first.
        $this->assertSame('Primary', $outcome->providerName);
    }

    /**
     * The verification screens and slip components were written against the
     * provider-native NIMC spellings. A successful, charged-for verification
     * that renders blank fields is the worst kind of failure, so the routed
     * provider emits both those names and the canonical ones.
     */
    public function test_the_result_carries_both_canonical_and_screen_field_names(): void
    {
        $provider = VerificationProvider::create([
            'name' => 'Prembly', 'slug' => 'prembly', 'base_url' => 'https://api.prembly.test',
            'auth_type' => 'header_key', 'auth_config' => ['header_name' => 'x-api-key'],
            'credentials' => ['token' => 't'], 'is_active' => true, 'priority' => 10, 'timeout_seconds' => 30,
        ]);
        VerificationEndpoint::create([
            'provider_id' => $provider->getKey(), 'service' => 'nin.verify', 'http_method' => 'POST',
            'path' => '/verification/vnin-basic', 'body_type' => 'json',
            'field_map' => ['nin' => 'number'], 'is_active' => true,
        ]);
        VerificationRoute::create(['service' => 'nin.verify', 'provider_id' => $provider->getKey(), 'position' => 1]);

        Http::fake(['api.prembly.test/*' => Http::response([
            'status' => true,
            'data' => [
                'first_name' => 'ADA', 'middle_name' => 'NGOZI', 'surname' => 'OKAFOR',
                'birthdate' => '02-04-1990', 'telephoneno' => '08012345678',
                'residence_address' => '5 BROAD STREET', 'gender' => 'f',
            ],
        ], 200)]);

        $result = app(\App\Services\Nin\Providers\RoutedProvider::class)->verifyByNin('12345678901');

        $this->assertTrue($result->success);

        // Canonical names, for new code.
        $this->assertSame('OKAFOR', $result->data['last_name']);
        $this->assertSame('1990-04-02', $result->data['date_of_birth']);
        $this->assertSame('FEMALE', $result->data['gender']);

        // The names the existing screens and slips bind to.
        $this->assertSame('OKAFOR', $result->data['surname']);
        $this->assertSame('ADA', $result->data['firstname']);
        $this->assertSame('NGOZI', $result->data['middlename']);
        $this->assertSame('1990-04-02', $result->data['dob']);
        $this->assertSame('08012345678', $result->data['telephoneno']);
        $this->assertSame('5 BROAD STREET', $result->data['residence_AdressLine']);
        $this->assertSame('ADA NGOZI', $result->data['othernames']);
        $this->assertSame('Prembly', $result->data['provider']);
    }

    public function test_max_attempts_caps_the_chain(): void
    {
        $this->twoProviders();
        VerificationSetting::put('failover_max_attempts', 1);

        Http::fake([
            'primary.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200),
            'backup.test/*' => Http::response(['status' => 'success', 'data' => ['bvn' => '1']], 200),
        ]);

        $outcome = app(VerificationDispatcher::class)->verify('bvn.verify', ['bvn' => '12345678901']);

        $this->assertFalse($outcome->isSuccess());
        $this->assertCount(1, $outcome->attempts);
    }
}
