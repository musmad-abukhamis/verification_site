<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DataSetting;
use App\Models\DataTransaction;
use App\Models\NetworkVendorMapping;
use App\Models\Plan;
use App\Models\PlanVendorMapping;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Services\DataCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function seedRouting(): array
    {
        $plan = Plan::create([
            'id' => 1, 'network' => 'mtn', 'type' => 'SME', 'name' => '1GB',
            'price' => 700, 'agent_price' => 650, 'api_price' => 600,
            'validity' => '30 Days', 'status' => 'on', 'plan_status' => 'on',
        ]);
        $vendor = Vendor::create([
            'name' => 'va', 'base_url' => 'https://va.test/api/data', 'driver' => 'token_style_a',
            'credentials' => ['key' => 'k'], 'is_active' => true, 'priority' => 1,
        ]);
        PlanVendorMapping::create(['plan_id' => $plan->id, 'vendor_id' => $vendor->id, 'external_plan_id' => '2']);
        NetworkVendorMapping::create(['network' => 'mtn', 'vendor_id' => $vendor->id, 'external_network_code' => '1']);
        VendorRoute::create(['network' => 'mtn', 'type' => 'SME', 'vendor_id' => $vendor->id, 'position' => 1]);
        DataSetting::put('failover_enabled', false);

        return [$plan, $vendor];
    }

    /* ------------------------------------------------------------------ API */

    public function test_api_requires_token(): void
    {
        $this->postJson('/api/v1/data', [])->assertStatus(401);
    }

    public function test_api_rejects_non_api_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER, 'apitoken' => 'tok-user', 'balance' => 5000]);

        $this->withToken('tok-user')
            ->postJson('/api/v1/data', ['plan_id' => 1, 'phone' => '08031234567', 'client_ref' => (string) Str::uuid()])
            ->assertStatus(401);
    }

    /**
     * An integrator wired to another data API should be able to point it at us
     * without renaming a single field: network as a numeric id, plan as
     * data_plan, phone as mobile_number, and their own non-UUID request id.
     */
    public function test_api_accepts_another_providers_body_shape(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'network' => 1,
            'mobile_number' => '+2348031234567',
            'data_plan' => $plan->id,
            'bypass' => false,
            'request-id' => 'Data_12345678900',
        ])->assertStatus(201)->assertJsonPath('status', 'success');

        $txn = DataTransaction::first();

        $this->assertSame('08031234567', $txn->phone);
        $this->assertSame('mtn', $txn->network);
        $this->assertSame(4400.0, (float) $user->fresh()->balance);
    }

    /**
     * The flat fields other data APIs return, so an integrator does not have to
     * rewrite their response parsing either. The caller's own reference must
     * come back verbatim -- that is how they match it to their order.
     */
    public function test_the_response_carries_the_compatible_flat_fields(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $response = $this->withToken('tok-api')->postJson('/api/v1/data', [
            'network' => 1,
            'phone' => '08031234567',
            'data_plan' => $plan->id,
            'request-id' => 'Data_12345678900',
        ])->assertStatus(201);

        $response
            ->assertJsonPath('request-id', 'Data_12345678900')
            ->assertJsonPath('network', 'MTN')
            ->assertJsonPath('dataplan', '1GB')
            ->assertJsonPath('plan_type', 'SME')
            ->assertJsonPath('phone_number', '08031234567')
            ->assertJsonPath('amount', '600')
            ->assertJsonPath('system', 'API')
            ->assertJsonPath('wallet_vending', 'wallet')
            ->assertJsonPath('newbal', 4400);

        // The original envelope is untouched, so existing integrations keep working.
        $response->assertJsonPath('status', 'success')
            ->assertJsonPath('data.reference', DataTransaction::first()->id);
    }

    public function test_the_status_lookup_returns_the_same_flat_fields(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'network' => 1,
            'phone' => '08031234567',
            'data_plan' => $plan->id,
            'ref' => 'ORDER-55',
        ])->assertStatus(201);

        $reference = DataTransaction::first()->id;

        $this->withToken('tok-api')->getJson("/api/v1/data/{$reference}")
            ->assertOk()
            ->assertJsonPath('request-id', 'ORDER-55')
            ->assertJsonPath('network', 'MTN')
            ->assertJsonPath('transaction_status', 'success');
    }

    /**
     * The caller's own request id is the idempotency key, so replaying it must
     * not charge twice -- even though it is not a UUID.
     */
    public function test_a_repeated_request_id_does_not_charge_twice(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $body = [
            'network' => 1,
            'phone' => '08031234567',
            'data_plan' => $plan->id,
            'ref' => 'ORDER-77',
        ];

        $this->withToken('tok-api')->postJson('/api/v1/data', $body)->assertStatus(201);
        $this->withToken('tok-api')->postJson('/api/v1/data', $body);

        $this->assertSame(1, DataTransaction::count());
        $this->assertSame(4400.0, (float) $user->fresh()->balance);
    }

    public function test_a_network_that_disagrees_with_the_plan_is_rejected(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake();

        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        // Plan 1 is MTN; 3 is Glo.
        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'network' => 3,
            'phone' => '08031234567',
            'data_plan' => $plan->id,
        ])->assertStatus(422);

        $this->assertSame(0, DataTransaction::count());
    }

    public function test_an_unknown_network_id_is_rejected(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake();

        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'network' => 7,
            'phone' => '08031234567',
            'data_plan' => $plan->id,
        ])->assertStatus(422);
    }

    /* ------------------------------------------------- public plan id (code) */

    public function test_a_new_plan_gets_a_short_public_id(): void
    {
        $plan = Plan::create([
            'network' => 'mtn', 'type' => 'SME', 'name' => '2GB', 'price' => 1400,
            'agent_price' => 1300, 'api_price' => 1200, 'validity' => '30 Days',
            'status' => 'on', 'plan_status' => 'on',
        ]);

        $this->assertNotNull($plan->code);
        $this->assertGreaterThanOrEqual(1, $plan->code);
        $this->assertLessThanOrEqual(Plan::MAX_CODE, $plan->code);
    }

    public function test_public_plan_ids_do_not_repeat(): void
    {
        $codes = collect(range(1, 5))->map(fn (int $i) => Plan::create([
            'network' => 'mtn', 'type' => 'SME', 'name' => "plan {$i}", 'price' => 100,
            'agent_price' => 100, 'api_price' => 100, 'status' => 'on', 'plan_status' => 'on',
        ])->code);

        $this->assertSame($codes->unique()->count(), $codes->count());
    }

    /**
     * A code is stored in integrators' own systems, so handing a deleted plan's
     * number to a new plan would silently start selling them a different bundle.
     */
    public function test_a_deleted_plans_public_id_is_not_reissued(): void
    {
        $first = Plan::create([
            'network' => 'mtn', 'type' => 'SME', 'name' => 'gone', 'price' => 100,
            'agent_price' => 100, 'api_price' => 100, 'status' => 'on', 'plan_status' => 'on',
        ]);
        $retired = $first->code;
        $first->delete();

        $second = Plan::create([
            'network' => 'mtn', 'type' => 'SME', 'name' => 'new', 'price' => 100,
            'agent_price' => 100, 'api_price' => 100, 'status' => 'on', 'plan_status' => 'on',
        ]);

        $this->assertNotSame($retired, $second->code);
    }

    public function test_an_admin_can_choose_the_public_plan_id(): void
    {
        $this->actingAs($this->admin())->post('/admin/dataplan', [
            'code' => 250,
            'network' => 'mtn', 'type' => 'SME', 'name' => '3GB', 'price' => 2000,
            'agent_price' => 1900, 'api_price' => 1800, 'validity' => '30 Days',
            'status' => 'on', 'plan_status' => 'on', 'mappings' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame(250, Plan::where('name', '3GB')->first()->code);
    }

    public function test_a_duplicate_public_plan_id_is_rejected(): void
    {
        [$plan] = $this->seedRouting();

        $this->actingAs($this->admin())->post('/admin/dataplan', [
            'code' => $plan->code,
            'network' => 'mtn', 'type' => 'SME', 'name' => 'clash', 'price' => 100,
            'agent_price' => 100, 'api_price' => 100,
            'status' => 'on', 'plan_status' => 'on', 'mappings' => [],
        ])->assertSessionHasErrors('code');
    }

    /**
     * The purchase endpoint takes the PUBLIC id, so an internal primary key
     * that happens to differ must not be accepted in its place.
     */
    public function test_the_api_buys_by_public_plan_id(): void
    {
        $plan = Plan::create([
            'code' => 42,
            'network' => 'mtn', 'type' => 'SME', 'name' => '1GB', 'price' => 700,
            'agent_price' => 650, 'api_price' => 600, 'status' => 'on', 'plan_status' => 'on',
        ]);

        // The two identifiers must differ, or this proves nothing.
        $this->assertNotSame($plan->code, $plan->id);
        $vendor = Vendor::create([
            'name' => 'va', 'base_url' => 'https://va.test/api/data', 'driver' => 'token_style_a',
            'credentials' => ['key' => 'k'], 'is_active' => true, 'priority' => 1,
        ]);
        PlanVendorMapping::create(['plan_id' => $plan->id, 'vendor_id' => $vendor->id, 'external_plan_id' => '2']);
        NetworkVendorMapping::create(['network' => 'mtn', 'vendor_id' => $vendor->id, 'external_network_code' => '1']);
        VendorRoute::create(['network' => 'mtn', 'type' => 'SME', 'vendor_id' => $vendor->id, 'position' => 1]);
        DataSetting::put('failover_enabled', false);

        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        // The internal key is not a valid plan_id.
        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'phone' => '08031234567', 'data_plan' => $plan->id, 'ref' => 'a',
        ])->assertStatus(422);

        $this->withToken('tok-api')->postJson('/api/v1/data', [
            'phone' => '08031234567', 'data_plan' => $plan->code, 'ref' => 'b',
        ])->assertStatus(201);

        // Stored against the internal key, addressed by the public one.
        $this->assertSame($plan->id, DataTransaction::first()->plan_id);
    }

    public function test_the_plans_endpoint_lists_public_ids_and_api_prices(): void
    {
        [$plan] = $this->seedRouting();
        User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $response = $this->withToken('tok-api')->getJson('/api/v1/plans')->assertOk();

        $row = collect($response->json('data.plans'))->firstWhere('plan_id', $plan->code);

        $this->assertSame('MTN', $row['network']);
        $this->assertSame(1, $row['network_id']);
        $this->assertSame(600, $row['price']);   // API rate, not the retail 700
    }

    public function test_api_purchase_succeeds(): void
    {
        [$plan] = $this->seedRouting();
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['role' => UserRole::API, 'apitoken' => 'tok-api', 'balance' => 5000]);

        $response = $this->withToken('tok-api')->postJson('/api/v1/data', [
            'plan_id' => $plan->id,
            'phone' => '08031234567',
            'client_ref' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'success');
        // API price (600) charged, not user price (700).
        $this->assertSame(4400.0, (float) $user->fresh()->balance);
        $this->assertSame('success', DataTransaction::first()->fresh()->status);
    }

    /* --------------------------------------------------------------- admin */

    public function test_admin_can_create_vendor_with_encrypted_credentials(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.vendors.store'), [
                'name' => 'New Vendor', 'base_url' => 'https://nv.test/api', 'driver' => 'token_style_a',
                'priority' => 5, 'is_active' => true, 'credentials' => ['key' => 'super-secret'],
            ])
            ->assertRedirect();

        $vendor = Vendor::where('name', 'New Vendor')->first();
        $this->assertNotNull($vendor);
        $this->assertSame('super-secret', $vendor->credentials['key']); // decrypts
    }

    public function test_admin_vendor_update_keeps_secret_when_blank(): void
    {
        $vendor = Vendor::create([
            'name' => 'v', 'base_url' => 'https://v.test', 'driver' => 'token_style_a',
            'credentials' => ['key' => 'keep-me'], 'is_active' => true, 'priority' => 1,
        ]);

        $this->actingAs($this->admin())->put(route('admin.vendors.update', $vendor->id), [
            'name' => 'v2', 'base_url' => 'https://v.test', 'driver' => 'token_style_a',
            'priority' => 1, 'is_active' => true, 'credentials' => ['key' => ''],
        ])->assertRedirect();

        $this->assertSame('keep-me', $vendor->fresh()->credentials['key']);
        $this->assertSame('v2', $vendor->fresh()->name);
    }

    public function test_admin_plan_store_syncs_mappings_and_flushes_cache(): void
    {
        $vendor = Vendor::create([
            'name' => 'va', 'base_url' => 'https://va.test', 'driver' => 'token_style_a',
            'credentials' => ['key' => 'k'], 'is_active' => true, 'priority' => 1,
        ]);

        $this->actingAs($this->admin())->post(route('admin.dataplan.store'), [
            'network' => 'MTN', 'type' => 'SME', 'name' => '2GB',
            'price' => 1400, 'agent_price' => 1300, 'api_price' => 1200, 'validity' => '30 Days',
            'status' => 'on', 'plan_status' => 'on',
            'mappings' => [
                ['vendor_id' => $vendor->id, 'external_plan_id' => '99'],
            ],
        ])->assertRedirect();

        $plan = Plan::where('name', '2GB')->first();
        $this->assertSame('mtn', $plan->network); // lowercased
        $this->assertDatabaseHas('plan_vendor_mappings', ['plan_id' => $plan->id, 'vendor_id' => $vendor->id, 'external_plan_id' => '99']);
        // Cache reflects the new plan (flush happened on save).
        $this->assertTrue(collect(DataCache::catalog())->contains(fn ($p) => $p['name'] === '2GB'));
    }

    public function test_admin_can_update_routing_matrix(): void
    {
        [$plan, $vendor] = $this->seedRouting();
        $vendorB = Vendor::create([
            'name' => 'vb', 'base_url' => 'https://vb.test', 'driver' => 'token_style_b',
            'credentials' => ['key' => 'k'], 'is_active' => true, 'priority' => 2,
        ]);

        $this->actingAs($this->admin())->put(route('admin.data.routing.update'), [
            'routes' => [
                ['network' => 'mtn', 'type' => 'SME', 'vendor_ids' => [$vendorB->id, $vendor->id]],
            ],
        ])->assertRedirect();

        $this->assertSame($vendorB->id, VendorRoute::where('network', 'mtn')->where('type', 'SME')->where('position', 1)->value('vendor_id'));
        $this->assertSame($vendor->id, VendorRoute::where('network', 'mtn')->where('type', 'SME')->where('position', 2)->value('vendor_id'));
    }

    public function test_admin_settings_and_prefixes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.data.settings.update'), [
            'failover_enabled' => true, 'failover_max_attempts' => 3,
            'reconcile_cutoff_minutes' => 90, 'requery_interval_minutes' => 10,
        ])->assertRedirect();

        $this->assertTrue(DataSetting::bool('failover_enabled'));
        $this->assertSame(90, DataSetting::int('reconcile_cutoff_minutes'));

        $this->actingAs($admin)->post(route('admin.data.prefixes.add'), ['network' => 'mtn', 'prefix' => '0999'])->assertRedirect();
        $this->assertDatabaseHas('network_prefixes', ['network' => 'mtn', 'prefix' => '0999']);

        $this->actingAs($admin)->delete(route('admin.data.prefixes.remove'), ['network' => 'mtn', 'prefix' => '0999'])->assertRedirect();
        $this->assertDatabaseMissing('network_prefixes', ['network' => 'mtn', 'prefix' => '0999']);
    }

    public function test_admin_wallet_credit_writes_ledger(): void
    {
        $user = User::factory()->create(['balance' => 1000]);

        $this->actingAs($this->admin())
            ->post(route('admin.data-wallet.credit', $user->id), ['amount' => 500])
            ->assertRedirect();

        $this->assertSame(1500.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('wallet_entries', ['user_id' => $user->id, 'direction' => 'credit', 'reason' => 'admin_credit']);
    }

    public function test_admin_wallet_debit_guards_insufficient(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $this->actingAs($this->admin())
            ->post(route('admin.data-wallet.debit', $user->id), ['amount' => 500])
            ->assertSessionHasErrors('amount');

        $this->assertSame(100.0, (float) $user->fresh()->balance);
    }

    public function test_non_admin_cannot_reach_admin_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.vendors.index'))->assertRedirect();
    }
}
