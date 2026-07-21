<?php

namespace Tests\Feature\Nin;

use App\Enums\UserRole;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\Nin\NinProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every NIN service and slip is priced from service_prices -- the table
 * Admin > Service Prices edits -- and the price depends on the caller's role.
 *
 * Before this, prices came from four places (verifyapiconfiq, two config files
 * and per-provider config), none of which could express a per-role rate.
 */
class NinServicePricingTest extends TestCase
{
    use RefreshDatabase;

    private function price(string $service, float $price, string $role = ServicePrice::BASE, bool $active = true): ServicePrice
    {
        ServicePrice::forgetCache();

        return ServicePrice::updateOrCreate(
            ['service' => $service, 'role' => $role],
            ['price' => $price, 'is_active' => $active],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        ServicePrice::forgetCache();
    }

    protected function tearDown(): void
    {
        ServicePrice::forgetCache();

        parent::tearDown();
    }

    public function test_a_user_pays_the_base_price(): void
    {
        $this->price('nin.verify', 75);

        Http::fake(['*' => Http::response(['nin' => '12345678901'])]);

        $user = User::factory()->create(['balance' => 1000, 'role' => UserRole::USER]);

        $this->actingAs($user)
            ->post('/nin/verify/v1', ['idType' => 'nin', 'idValue' => '12345678901'])
            ->assertSessionHasNoErrors();

        $this->assertSame(925.0, (float) $user->fresh()->balance);
    }

    /**
     * The point of the whole change: the same service, a cheaper rate.
     */
    public function test_a_role_with_an_override_pays_the_override(): void
    {
        $this->price('nin.verify', 75);
        $this->price('nin.verify', 40, UserRole::AGENT->value);

        Http::fake(['*' => Http::response(['nin' => '12345678901'])]);

        $agent = User::factory()->create(['balance' => 1000, 'role' => UserRole::AGENT]);

        $this->actingAs($agent)
            ->post('/nin/verify/v1', ['idType' => 'nin', 'idValue' => '12345678901'])
            ->assertSessionHasNoErrors();

        $this->assertSame(960.0, (float) $agent->fresh()->balance);
    }

    public function test_a_role_without_an_override_falls_back_to_base(): void
    {
        $this->price('nin.verify', 75);
        $this->price('nin.verify', 40, UserRole::AGENT->value);

        $api = User::factory()->create(['role' => UserRole::API]);

        $this->assertSame(75.0, ServicePrice::priceForUser('nin.verify', $api));
    }

    /**
     * A leftover role override must not resurrect a service the admin switched
     * off -- off has to mean off for everyone.
     */
    public function test_switching_a_service_off_beats_any_role_override(): void
    {
        $this->price('nin.verify', 75, ServicePrice::BASE, active: false);
        $this->price('nin.verify', 40, UserRole::AGENT->value);

        $agent = User::factory()->create(['role' => UserRole::AGENT]);

        $this->assertNull(ServicePrice::priceForUser('nin.verify', $agent));
    }

    /**
     * Switching a service off used to discard its price, because "inactive" was
     * stored as a null price. is_active is its own column now.
     */
    public function test_switching_a_service_off_keeps_its_price(): void
    {
        $this->price('nin.verify', 75);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->put('/admin/service-prices/nin.verify', ['price' => 75, 'is_active' => false])
            ->assertSessionHasNoErrors();

        $row = ServicePrice::where('service', 'nin.verify')->where('role', ServicePrice::BASE)->first();

        $this->assertFalse($row->is_active);
        $this->assertSame(75.0, (float) $row->price);
    }

    public function test_an_unpriced_service_is_refused_without_charging(): void
    {
        // The migration backfills a base row for every service, so a service is
        // only ever unpriced if someone removed it.
        ServicePrice::where('service', 'nin.verify')->delete();
        ServicePrice::forgetCache();

        Http::fake();

        $user = User::factory()->create(['balance' => 1000]);

        $this->actingAs($user)
            ->post('/nin/verify/v1', ['idType' => 'nin', 'idValue' => '12345678901'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    public function test_each_verification_method_has_its_own_price(): void
    {
        $this->price('nin.verify', 75);
        $this->price('nin.phone', 120);
        $this->price('nin.demographic', 200);

        $user = User::factory()->create(['role' => UserRole::USER]);
        $provider = app(NinProviderManager::class)->get('prembly');

        $this->assertSame(75.0, $provider->priceFor('nin', $user));
        $this->assertSame(120.0, $provider->priceFor('phone', $user));
        $this->assertSame(200.0, $provider->priceFor('demographic', $user));
    }

    public function test_every_provider_charges_the_same_fee_for_a_method(): void
    {
        $this->price('nin.verify', 75);

        $user = User::factory()->create();
        $manager = app(NinProviderManager::class);

        $this->assertSame(
            $manager->get('prembly')->priceFor('nin', $user),
            $manager->get('arewasmart')->priceFor('nin', $user),
        );
    }

    /**
     * Slips are services too, so they get role pricing for free.
     */
    public function test_slip_prices_are_role_aware(): void
    {
        $this->price('slip.premium', 300);
        $this->price('slip.premium', 150, UserRole::AGENT->value);

        $agent = User::factory()->create(['role' => UserRole::AGENT]);
        $retail = User::factory()->create(['role' => UserRole::USER]);

        $this->assertSame(150.0, ServicePrice::priceForUser('slip.premium', $agent));
        $this->assertSame(300.0, ServicePrice::priceForUser('slip.premium', $retail));
    }

    /**
     * Blanking an override deletes the row so the role returns to base pricing.
     * That is distinct from an override of 0, which means free.
     */
    public function test_clearing_an_override_returns_the_role_to_base(): void
    {
        $this->price('nin.verify', 75);
        $this->price('nin.verify', 40, UserRole::AGENT->value);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->put('/admin/service-prices/nin.verify', [
            'price' => 75,
            'is_active' => true,
            'overrides' => ['AGENT' => null],
        ])->assertSessionHasNoErrors();

        $agent = User::factory()->create(['role' => UserRole::AGENT]);

        $this->assertSame(75.0, ServicePrice::priceForUser('nin.verify', $agent));
        $this->assertDatabaseMissing('service_prices', ['service' => 'nin.verify', 'role' => 'AGENT']);
    }

    public function test_an_override_of_zero_is_a_real_price(): void
    {
        $this->price('nin.verify', 75);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->put('/admin/service-prices/nin.verify', [
            'price' => 75,
            'is_active' => true,
            'overrides' => ['API' => 0],
        ])->assertSessionHasNoErrors();

        $api = User::factory()->create(['role' => UserRole::API]);

        $this->assertSame(0.0, ServicePrice::priceForUser('nin.verify', $api));
    }

    /**
     * BVN services moved onto the same table, so they get role pricing too.
     */
    public function test_bvn_services_are_role_aware(): void
    {
        $this->price('bvn.mod.name', 3000);
        $this->price('bvn.mod.name', 1800, UserRole::AGENT->value);

        $agent = User::factory()->create(['role' => UserRole::AGENT]);
        $retail = User::factory()->create(['role' => UserRole::USER]);

        $this->assertSame(1800.0, ServicePrice::priceForUser('bvn.mod.name', $agent));
        $this->assertSame(3000.0, ServicePrice::priceForUser('bvn.mod.name', $retail));
    }

    /**
     * Each admin page edits only its own services, so the BVN screen cannot be
     * used to rewrite NIN pricing.
     */
    public function test_the_bvn_page_cannot_edit_a_nin_service(): void
    {
        $this->price('nin.verify', 75);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->put('/admin/bvn-prices/nin.verify', ['price' => 1, 'is_active' => true])
            ->assertSessionHasErrors('price');

        $this->assertSame(75.0, ServicePrice::priceFor('nin.verify'));
    }

    public function test_the_bvn_page_saves_a_bvn_service(): void
    {
        $this->price('bvn.retrieve.id', 500);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)->put('/admin/bvn-prices/bvn.retrieve.id', [
            'price' => 750,
            'is_active' => true,
            'overrides' => ['API' => 400],
        ])->assertSessionHasNoErrors();

        $api = User::factory()->create(['role' => UserRole::API]);

        $this->assertSame(750.0, ServicePrice::priceFor('bvn.retrieve.id'));
        $this->assertSame(400.0, ServicePrice::priceForUser('bvn.retrieve.id', $api));
    }

    public function test_an_unknown_service_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->put('/admin/service-prices/nin.nonsense', ['price' => 5, 'is_active' => true])
            ->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('service_prices', ['service' => 'nin.nonsense']);
    }

    /**
     * Rows are cached for 5 minutes, so saving in the admin has to bust it --
     * otherwise users keep paying the old fee.
     */
    public function test_saving_a_price_takes_effect_immediately(): void
    {
        $this->price('nin.verify', 75);

        $user = User::factory()->create(['role' => UserRole::USER]);
        $this->assertSame(75.0, ServicePrice::priceForUser('nin.verify', $user));

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->put('/admin/service-prices/nin.verify', ['price' => 250, 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(250.0, ServicePrice::priceForUser('nin.verify', $user));
    }
}
