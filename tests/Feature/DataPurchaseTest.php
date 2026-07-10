<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\ProcessDataPurchase;
use App\Jobs\ReconcilePendingTransactions;
use App\Models\DataSetting;
use App\Models\DataTransaction;
use App\Models\NetworkVendorMapping;
use App\Models\Plan;
use App\Models\PlanVendorMapping;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Models\WalletEntry;
use App\Services\DataPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'id' => 1,
            'network' => 'mtn',
            'type' => 'SME',
            'name' => '1GB',
            'price' => 700,
            'agent_price' => 650,
            'api_price' => 600,
            'validity' => '30 Days',
            'status' => 'on',
            'plan_status' => 'on',
        ]);

        $this->settings([
            'failover_enabled' => '0',
            'failover_max_attempts' => '0',
            'reconcile_cutoff_minutes' => '120',
        ]);
    }

    /* ------------------------------------------------------------- helpers */

    private function settings(array $values): void
    {
        foreach ($values as $k => $v) {
            DataSetting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        DataSetting::flushCache();
    }

    private function vendor(string $host, string $driver = 'token_style_a', bool $active = true, int $priority = 1): Vendor
    {
        return Vendor::create([
            'name' => $host,
            'base_url' => "https://{$host}.test/api/data",
            'driver' => $driver,
            'credentials' => ['key' => 'secret-'.$host],
            'is_active' => $active,
            'priority' => $priority,
        ]);
    }

    private function route(Vendor $vendor, int $position, string $planCode = '2', string $netCode = '1'): void
    {
        PlanVendorMapping::create(['plan_id' => $this->plan->id, 'vendor_id' => $vendor->id, 'external_plan_id' => $planCode]);
        NetworkVendorMapping::firstOrCreate(['network' => 'mtn', 'vendor_id' => $vendor->id], ['external_network_code' => $netCode]);
        VendorRoute::create(['network' => 'mtn', 'type' => 'SME', 'vendor_id' => $vendor->id, 'position' => $position]);
    }

    private function purchase(User $user, array $overrides = []): DataTransaction
    {
        return app(DataPurchaseService::class)->initiate($user, array_merge([
            'plan_id' => $this->plan->id,
            'phone' => '08031234567',
            'ported' => false,
            'client_ref' => (string) Str::uuid(),
        ], $overrides));
    }

    /* --------------------------------------------------------------- tests */

    public function test_price_is_resolved_from_role_server_side(): void
    {
        $this->vendor('va');
        $this->route(Vendor::first(), 1);
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $agent = User::factory()->create(['role' => UserRole::AGENT, 'balance' => 5000]);
        $txn = $this->purchase($agent);

        $this->assertSame(650.0, (float) $txn->price); // agent_price, not price(700)/api_price(600)
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $this->actingAs($user)
            ->post(route('buy-data.store'), [
                'plan_id' => $this->plan->id,
                'phone' => '08031234567',
                'ported' => false,
                'client_ref' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('balance');

        $this->assertSame(100.0, (float) $user->fresh()->balance);
        $this->assertDatabaseCount('data_transactions', 0);
    }

    public function test_duplicate_client_ref_is_idempotent(): void
    {
        $this->vendor('va');
        $this->route(Vendor::first(), 1);
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['balance' => 5000]);
        $ref = (string) Str::uuid();

        $first = $this->purchase($user, ['client_ref' => $ref]);
        $second = $this->purchase($user, ['client_ref' => $ref]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('data_transactions', 1);
        // Charged only once.
        $this->assertSame(4300.0, (float) $user->fresh()->balance);
    }

    public function test_purchase_is_queued_and_starts_pending(): void
    {
        Queue::fake();
        $this->vendor('va');
        $this->route(Vendor::first(), 1);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user);

        $this->assertSame('pending', $txn->status);
        Queue::assertPushed(ProcessDataPurchase::class);
        // Debited immediately, even though fulfilment is queued.
        $this->assertSame(4300.0, (float) $user->fresh()->balance);
    }

    public function test_successful_purchase_saves_beneficiary_and_ledger(): void
    {
        $this->vendor('va');
        $this->route(Vendor::first(), 1);
        Http::fake(['*' => Http::response(['status' => 'successful', 'reference' => 'X1'], 200)]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user);

        $this->assertSame('success', $txn->fresh()->status);
        $this->assertDatabaseHas('beneficiaries', ['user_id' => $user->id, 'phone' => '08031234567']);
        $this->assertSame(1, WalletEntry::where('user_id', $user->id)->where('direction', 'debit')->count());
    }

    public function test_explicit_fail_triggers_failover_when_enabled(): void
    {
        $this->settings(['failover_enabled' => '1']);
        $a = $this->vendor('va', priority: 1);
        $b = $this->vendor('vb', priority: 2);
        $this->route($a, 1);
        $this->route($b, 2);

        Http::fake([
            'va.test/*' => Http::response(['status' => 'failed', 'message' => 'no'], 200),
            'vb.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user)->fresh();

        $this->assertSame('success', $txn->status);
        $this->assertSame($b->id, $txn->vendor_id);
        $this->assertSame(2, $txn->attempts);
    }

    public function test_no_failover_when_disabled_refunds_after_first_fail(): void
    {
        $a = $this->vendor('va', priority: 1);
        $b = $this->vendor('vb', priority: 2);
        $this->route($a, 1);
        $this->route($b, 2);

        Http::fake([
            'va.test/*' => Http::response(['status' => 'failed'], 200),
            'vb.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user)->fresh();

        $this->assertSame('refunded', $txn->status);
        $this->assertSame(5000.0, (float) $user->fresh()->balance); // fully refunded
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'vb.test'));
    }

    public function test_timeout_does_not_failover_and_stays_processing(): void
    {
        $this->settings(['failover_enabled' => '1']);
        $a = $this->vendor('va', priority: 1);
        $b = $this->vendor('vb', priority: 2);
        $this->route($a, 1);
        $this->route($b, 2);

        Http::fake([
            'va.test/*' => Http::response('gateway error', 500), // ambiguous
            'vb.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user)->fresh();

        $this->assertSame('processing', $txn->status);
        $this->assertSame(4300.0, (float) $user->fresh()->balance); // not refunded
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'vb.test'));
    }

    public function test_reconciliation_marks_success(): void
    {
        $a = $this->vendor('va');
        $this->route($a, 1);
        Http::fake(['va.test/*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->stuckProcessing($user, $a);

        app(ReconcilePendingTransactions::class)->handle(app(\App\Services\Vendors\VendorDispatcher::class), app(\App\Services\WalletLedger::class));

        $this->assertSame('success', $txn->fresh()->status);
    }

    public function test_reconciliation_refunds_explicit_failure(): void
    {
        $a = $this->vendor('va');
        $this->route($a, 1);
        Http::fake(['va.test/*' => Http::response(['status' => 'failed'], 200)]);

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->stuckProcessing($user, $a);

        app(ReconcilePendingTransactions::class)->handle(app(\App\Services\Vendors\VendorDispatcher::class), app(\App\Services\WalletLedger::class));

        $this->assertSame('refunded', $txn->fresh()->status);
        $this->assertSame(5000.0, (float) $user->fresh()->balance);
    }

    public function test_reconciliation_refunds_unconfirmed_after_cutoff(): void
    {
        $a = $this->vendor('va');
        $this->route($a, 1);
        Http::fake(['va.test/*' => Http::response('still down', 500)]); // ambiguous

        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->stuckProcessing($user, $a, minutesAgo: 200); // older than 120 cutoff

        app(ReconcilePendingTransactions::class)->handle(app(\App\Services\Vendors\VendorDispatcher::class), app(\App\Services\WalletLedger::class));

        $this->assertSame('refunded_unconfirmed', $txn->fresh()->status);
        $this->assertSame(5000.0, (float) $user->fresh()->balance);
    }

    public function test_concurrent_purchases_cannot_overspend_one_balance(): void
    {
        $this->vendor('va');
        $this->route(Vendor::first(), 1);
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = User::factory()->create(['balance' => 700]); // exactly one plan

        $this->purchase($user); // ok, balance -> 0

        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $this->purchase($user); // must be rejected
    }

    public function test_ported_number_succeeds_against_contradicting_network(): void
    {
        $this->vendor('va');
        $this->route(Vendor::first(), 1);
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        // Phone starts 0805 (a GLO prefix) but the plan/network is MTN. The server
        // must NOT reject on prefix, and must persist the ported choice.
        $user = User::factory()->create(['balance' => 5000]);
        $txn = $this->purchase($user, ['phone' => '08051234567', 'ported' => true])->fresh();

        $this->assertSame('success', $txn->status);
        $this->assertTrue((bool) $txn->ported);
        $this->assertDatabaseHas('beneficiaries', [
            'user_id' => $user->id,
            'phone' => '08051234567',
            'is_ported' => true,
        ]);
    }

    private function stuckProcessing(User $user, Vendor $vendor, int $minutesAgo = 1): DataTransaction
    {
        // Debit then leave in the ambiguous processing state, as the job would.
        app(\App\Services\WalletLedger::class)->debit($user, 700, 'purchase');

        $txn = DataTransaction::create([
            'id' => 'Data_'.now()->timestamp.'_'.Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'status' => 'processing',
            'network' => 'mtn',
            'type' => 'SME',
            'plan_name' => '1GB',
            'price' => 700,
            'phone' => '08031234567',
            'ported' => false,
            'attempts' => 1,
            'oldbal' => 5000,
            'newbal' => 4300,
            'vendor_id' => $vendor->id,
        ]);

        // created_at isn't fillable, so back-date it explicitly for cutoff tests.
        $txn->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        return $txn;
    }
}
