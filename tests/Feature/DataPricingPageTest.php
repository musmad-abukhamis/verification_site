<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use App\Services\DataCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public price list. Priced for whoever is looking, using the same
 * role resolution the purchase itself uses -- so the number on the page is the
 * number they pay.
 */
class DataPricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DataCache::flush();
    }

    private function plan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'network' => 'mtn', 'type' => 'SME', 'name' => '1GB',
            'price' => 700, 'agent_price' => 650, 'api_price' => 600,
            'validity' => '30 Days', 'status' => 'on', 'plan_status' => 'on',
        ], $overrides));
    }

    private function plans($response): array
    {
        return $response->original->getData()['page']['props']['plans'];
    }

    public function test_the_page_is_public(): void
    {
        $this->plan();

        $this->get('/data-pricing')->assertOk();
    }

    public function test_it_lists_the_columns_asked_for(): void
    {
        $plan = $this->plan();

        $row = $this->plans($this->get('/data-pricing'))[0];

        $this->assertSame($plan->code, $row['plan_id']);
        $this->assertSame('mtn', $row['network']);
        $this->assertSame('1GB', $row['name']);
        $this->assertSame('SME', $row['type']);
        $this->assertSame('30 Days', $row['validity']);
        $this->assertSame(700.0, $row['price']);
    }

    public function test_a_visitor_sees_retail_prices(): void
    {
        $this->plan();

        $this->assertSame(700.0, $this->plans($this->get('/data-pricing'))[0]['price']);
    }

    public function test_an_agent_sees_their_own_rate(): void
    {
        $this->plan();
        $agent = User::factory()->create(['role' => UserRole::AGENT]);

        $this->assertSame(650.0, $this->plans($this->actingAs($agent)->get('/data-pricing'))[0]['price']);
    }

    public function test_an_api_reseller_sees_their_own_rate(): void
    {
        $this->plan();
        $reseller = User::factory()->create(['role' => UserRole::API]);

        $this->assertSame(600.0, $this->plans($this->actingAs($reseller)->get('/data-pricing'))[0]['price']);
    }

    /**
     * A hidden or unavailable plan must not be advertised at a price nobody can
     * actually buy at.
     */
    public function test_hidden_and_unavailable_plans_are_not_listed(): void
    {
        $this->plan(['name' => 'visible']);
        $this->plan(['name' => 'hidden', 'plan_status' => 'off']);
        $this->plan(['name' => 'unavailable', 'status' => 'off']);

        DataCache::flush();

        $names = array_column($this->plans($this->get('/data-pricing')), 'name');

        $this->assertSame(['visible'], $names);
    }

    public function test_networks_are_listed_for_the_section_filters(): void
    {
        $this->plan(['network' => 'mtn']);
        $this->plan(['network' => 'glo', 'name' => '2GB']);

        DataCache::flush();

        $networks = $this->get('/data-pricing')->original->getData()['page']['props']['networks'];

        $this->assertEqualsCanonicalizing(['mtn', 'glo'], (array) $networks);
    }
}
