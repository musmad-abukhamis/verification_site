<?php

namespace Tests\Feature\Nin;

use App\Models\NinServicePrice;
use App\Models\User;
use App\Models\VerifyApiConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every NIN service is priced from ninServicePrices -- the row Admin > Service
 * Prices edits. Before this, prices came from four different places
 * (verifyapiconfiq, two config files, and per-provider config), so editing the
 * admin page changed nothing users were actually charged.
 */
class NinServicePricingTest extends TestCase
{
    use RefreshDatabase;

    private function prices(array $overrides = []): NinServicePrice
    {
        NinServicePrice::forgetCache();

        return NinServicePrice::updateOrCreate(['id' => 'API1'], array_merge([
            'searchslip1' => '75',
            'phone_verify' => '120',
            'demo_verify' => '200',
            'ipe' => '90',
            'validation' => '65',
        ], $overrides));
    }

    protected function tearDown(): void
    {
        NinServicePrice::forgetCache();

        parent::tearDown();
    }

    public function test_nin_verification_is_charged_from_searchslip1(): void
    {
        $this->prices();

        // A stale slip price must not leak into the verification fee.
        VerifyApiConfig::updateOrCreate(['id' => 'API1'], ['pullingprice' => 999]);

        Http::fake(['*' => Http::response(['nin' => '12345678901', 'firstname' => 'JOHN'])]);

        $user = User::factory()->create(['balance' => 1000]);

        $this->actingAs($user)
            ->post('/nin/verify/v1', ['idType' => 'nin', 'idValue' => '12345678901'])
            ->assertSessionHasNoErrors();

        $this->assertSame(925.0, (float) $user->fresh()->balance);
    }

    public function test_a_service_with_no_configured_price_is_refused_without_charging(): void
    {
        $this->prices(['searchslip1' => null]);

        Http::fake();

        $user = User::factory()->create(['balance' => 1000]);

        $this->actingAs($user)
            ->post('/nin/verify/v1', ['idType' => 'nin', 'idValue' => '12345678901'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    /**
     * Phone and demographic verification each have their own field, so they can
     * be priced apart from verification by NIN.
     */
    public function test_each_method_is_charged_from_its_own_field(): void
    {
        $this->prices();

        $provider = app(\App\Services\Nin\NinProviderManager::class)->get('prembly');

        $this->assertSame(75.0, $provider->priceFor('nin'));
        $this->assertSame(120.0, $provider->priceFor('phone'));
        $this->assertSame(200.0, $provider->priceFor('demographic'));
    }

    public function test_every_provider_charges_the_same_fee_for_a_method(): void
    {
        $this->prices();

        $manager = app(\App\Services\Nin\NinProviderManager::class);

        $this->assertSame(
            $manager->get('prembly')->priceFor('nin'),
            $manager->get('arewasmart')->priceFor('nin'),
        );
    }

    public function test_an_unpriced_method_returns_null_rather_than_a_default(): void
    {
        $this->prices(['demo_verify' => null]);

        $provider = app(\App\Services\Nin\NinProviderManager::class)->get('prembly');

        $this->assertNull($provider->priceFor('demographic'));
    }

    /**
     * The row is cached for 5 minutes, so saving a price in the admin has to
     * bust it -- otherwise users keep paying the old fee.
     */
    public function test_saving_a_price_in_the_admin_takes_effect_immediately(): void
    {
        $this->prices();

        $provider = app(\App\Services\Nin\NinProviderManager::class)->get('prembly');
        $this->assertSame(75.0, $provider->priceFor('nin'));

        $admin = User::factory()->create(['role' => \App\Enums\UserRole::ADMIN]);

        $this->actingAs($admin)
            ->put('/admin/service-prices/searchslip1', ['price' => 250])
            ->assertSessionHasNoErrors();

        $this->assertSame(250.0, $provider->priceFor('nin'));
    }
}
