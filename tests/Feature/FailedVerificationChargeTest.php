<?php

namespace Tests\Feature;

use App\Models\FailedVerificationCharge;
use App\Models\ServicePrice;
use App\Models\User;
use App\Models\Validation;
use App\Models\VerificationSetting;
use App\Models\WalletHistory;
use App\Services\Bvn\BvnSearchService;
use App\Services\Verification\FailedVerificationChargeService;
use App\Services\Verification\FailureClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresVerificationProviders;
use Tests\TestCase;

/**
 * The failed-verification charge.
 *
 * The whole feature turns on one distinction, so most of these tests are about
 * it: the provider answered "no such record" (bill) versus nobody answered at
 * all (do not bill). Getting that backwards would charge customers for our own
 * outages, so the timeout / 5xx / unconfigured cases are tested at least as
 * hard as the billable ones.
 */
class FailedVerificationChargeTest extends TestCase
{
    use ConfiguresVerificationProviders;
    use RefreshDatabase;

    private const START_BALANCE = 10000.0;

    protected function setUp(): void
    {
        parent::setUp();

        VerificationSetting::put('failover_enabled', false);
        VerificationSetting::put('failed_charge_bvn_enabled', true);
        VerificationSetting::put('failed_charge_bvn_amount', '50.00');
        VerificationSetting::put('failed_charge_nin_enabled', true);
        VerificationSetting::put('failed_charge_nin_amount', '50.00');
    }

    private function user(float $balance = self::START_BALANCE): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => $balance])->save();

        foreach (['bvn.search.premium' => 100, 'nin.verify' => 100, 'nin.phone' => 100] as $service => $price) {
            ServicePrice::updateOrCreate(
                ['service' => $service, 'role' => ServicePrice::BASE],
                ['price' => $price, 'is_active' => true],
            );
        }

        ServicePrice::forgetCache();

        return $user;
    }

    private function bvnSearch(): BvnSearchService
    {
        return app(BvnSearchService::class);
    }

    /** The single debit rows written by this feature. */
    private function chargeLedgerFor(User $user)
    {
        return WalletHistory::where('userId', $user->getKey())
            ->whereIn('fundingtype', array_values(FailedVerificationChargeService::CATEGORIES))
            ->get();
    }

    // ---------------------------------------------------------------- BVN ---

    public function test_a_successful_bvn_verification_is_not_charged_the_failed_fee(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response([
            'status' => 'success',
            'data' => ['bvn' => '12345678901', 'surname' => 'DOE', 'firstname' => 'JANE'],
        ], 200)]);

        $result = $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertTrue($result['success']);
        // Only the ordinary 100 slip fee moved.
        $this->assertSame(self::START_BALANCE - 100, (float) $user->fresh()->balance);
        $this->assertCount(0, $this->chargeLedgerFor($user));
        $this->assertSame(0, FailedVerificationCharge::count());
    }

    public function test_a_bvn_record_not_found_is_charged_the_configured_amount(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(
            ['status' => false, 'message' => 'BVN not found'],
            200,
        )]);

        $result = $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertFalse($result['success']);
        $this->assertSame('verification_failed', $result['code']);

        // 100 debited then refunded, 50 charged: net -50.
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);

        $charge = FailedVerificationCharge::sole();
        $this->assertTrue($charge->charged);
        $this->assertSame(50.0, (float) $charge->amount);
        $this->assertSame(FailureClassification::RECORD_NOT_FOUND, $charge->classification);
        $this->assertSame('bvn', $charge->identity_type);

        // Exactly one debit, categorised, with the reference the user is shown.
        $ledger = $this->chargeLedgerFor($user);
        $this->assertCount(1, $ledger);
        $this->assertSame('debit', $ledger->first()->type);
        $this->assertSame('bvn_verification_failed', $ledger->first()->fundingtype);
        $this->assertSame(50.0, (float) $ledger->first()->amount);
        $this->assertSame($charge->wallet_history_id, $ledger->first()->id);

        // ...and the user is told, in the message the page already renders.
        $this->assertStringContainsString('₦50.00 has been deducted', $result['message']);
    }

    public function test_an_invalid_bvn_rejected_by_the_provider_is_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(
            ['status' => 'failed', 'message' => 'Invalid BVN supplied'],
            200,
        )]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);
        $this->assertTrue(FailedVerificationCharge::sole()->charged);
    }

    public function test_a_bvn_provider_timeout_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertFalse($result['success']);
        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance, 'a timeout must be fully refunded');
        $this->assertCount(0, $this->chargeLedgerFor($user));

        // The non-charge is on record, so the history can prove it.
        $charge = FailedVerificationCharge::sole();
        $this->assertFalse($charge->charged);
        $this->assertSame(FailureClassification::TIMEOUT, $charge->classification);
        $this->assertSame(FailedVerificationCharge::REASON_NOT_BILLABLE, $charge->reason);
    }

    public function test_a_bvn_provider_500_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['message' => 'Internal Server Error'], 500)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(FailureClassification::TIMEOUT, FailedVerificationCharge::sole()->classification);
    }

    public function test_an_unreadable_provider_reply_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        // An HTML error page / WAF block: ambiguous, not a "not found".
        Http::fake(['provider.test/*' => Http::response('<html>gateway</html>', 200)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertFalse(FailedVerificationCharge::sole()->charged);
    }

    public function test_an_unconfigured_chain_is_never_charged(): void
    {
        $user = $this->user();
        // No provider routed for bvn.verify at all.

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(FailureClassification::PROVIDER_ERROR, FailedVerificationCharge::sole()->classification);
    }

    /**
     * Our upstream account running dry is our problem. The customer's BVN may be
     * perfectly valid, so billing them for it would be indefensible.
     */
    public function test_a_decline_caused_by_our_own_upstream_balance_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(
            ['status' => false, 'message' => 'Insufficient balance on your API account'],
            200,
        )]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(FailureClassification::PROVIDER_ERROR, FailedVerificationCharge::sole()->classification);
    }

    public function test_a_failed_bvn_with_too_little_balance_never_goes_negative(): void
    {
        // Enough for the 100 slip fee, but the refund + 50 fee would overdraw if
        // the fee were larger than the refund. Push the fee above the balance.
        VerificationSetting::put('failed_charge_bvn_amount', '5000.00');

        $user = $this->user(120);
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $result = $this->bvnSearch()->search($user, '12345678901', 'premium');

        // Refunded in full, fee skipped — balance intact, never negative.
        $this->assertSame(120.0, (float) $user->fresh()->balance);
        $this->assertGreaterThanOrEqual(0, (float) $user->fresh()->balance);

        $charge = FailedVerificationCharge::sole();
        $this->assertFalse($charge->charged);
        $this->assertSame(FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE, $charge->reason);
        $this->assertStringContainsString('Insufficient wallet balance', $result['message']);
    }

    public function test_the_same_bvn_submitted_twice_is_only_debited_once(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');
        $second = $this->bvnSearch()->search($user, '12345678901', 'premium');

        // Two lookups, two slip-fee round trips, but one failed-verification fee.
        $this->assertCount(1, $this->chargeLedgerFor($user));
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);

        $charges = FailedVerificationCharge::orderBy('id')->get();
        $this->assertCount(2, $charges, 'both attempts are recorded');
        $this->assertTrue($charges[0]->charged);
        $this->assertFalse($charges[1]->charged);
        $this->assertSame(FailedVerificationCharge::REASON_DUPLICATE, $charges[1]->reason);
        $this->assertStringNotContainsString('has been deducted', $second['message']);
    }

    public function test_a_different_bvn_inside_the_window_is_still_charged(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');
        $this->bvnSearch()->search($user, '99999999999', 'premium');

        $this->assertCount(2, $this->chargeLedgerFor($user));
        $this->assertSame(self::START_BALANCE - 100, (float) $user->fresh()->balance);
    }

    public function test_disabling_bvn_charges_stops_the_deduction(): void
    {
        VerificationSetting::put('failed_charge_bvn_enabled', false);

        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $result = $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(0, FailedVerificationCharge::count());
        $this->assertStringNotContainsString('deducted', $result['message']);
    }

    public function test_a_zero_amount_charges_nothing_even_when_enabled(): void
    {
        VerificationSetting::put('failed_charge_bvn_amount', '0');

        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(0, FailedVerificationCharge::count());
    }

    public function test_changing_the_amount_applies_to_the_next_attempt(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->bvnSearch()->search($user, '11111111111', 'premium');
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);

        VerificationSetting::put('failed_charge_bvn_amount', '100.00');

        $this->bvnSearch()->search($user, '22222222222', 'premium');
        $this->assertSame(self::START_BALANCE - 150, (float) $user->fresh()->balance);
    }

    // ---------------------------------------------------------------- NIN ---

    private function verifyNin(User $user, string $nin = '12345678901')
    {
        return $this->actingAs($user)->post(route('nin.verify.store'), [
            'idType' => 'nin',
            'idValue' => $nin,
        ]);
    }

    public function test_a_successful_nin_verification_is_not_charged_the_failed_fee(): void
    {
        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response([
            'status' => true,
            'data' => ['nin' => '12345678901', 'surname' => 'DOE', 'firstname' => 'JOHN'],
        ], 200)]);

        $this->verifyNin($user);

        $this->assertSame(self::START_BALANCE - 100, (float) $user->fresh()->balance);
        $this->assertSame(0, FailedVerificationCharge::count());
    }

    public function test_a_nin_record_not_found_is_charged_and_recorded(): void
    {
        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response(
            ['status' => false, 'message' => 'NIN not found'],
            200,
        )]);

        $response = $this->verifyNin($user);

        $response->assertSessionHasErrors('message');
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);

        $charge = FailedVerificationCharge::sole();
        $this->assertTrue($charge->charged);
        $this->assertSame('nin', $charge->identity_type);
        $this->assertSame('nin.verify', $charge->service);
        $this->assertSame(FailureClassification::RECORD_NOT_FOUND, $charge->classification);

        // Linked to the history row the user sees on the NIN page.
        $this->assertNotNull($charge->record_id);
        $this->assertSame((string) Validation::sole()->id, $charge->record_id);

        $this->assertCount(1, $this->chargeLedgerFor($user));
        $this->assertSame('nin_verification_failed', $this->chargeLedgerFor($user)->first()->fundingtype);
    }

    public function test_a_nin_provider_timeout_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->verifyNin($user);

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertCount(0, $this->chargeLedgerFor($user));
        $this->assertSame(FailureClassification::TIMEOUT, FailedVerificationCharge::sole()->classification);
    }

    public function test_a_nin_provider_500_is_never_charged(): void
    {
        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response(['message' => 'Server Error'], 500)]);

        $this->verifyNin($user);

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertFalse(FailedVerificationCharge::sole()->charged);
    }

    public function test_a_failed_nin_with_too_little_balance_never_goes_negative(): void
    {
        VerificationSetting::put('failed_charge_nin_amount', '5000.00');

        $user = $this->user(150);
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->verifyNin($user);

        $this->assertSame(150.0, (float) $user->fresh()->balance);
        $this->assertSame(
            FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE,
            FailedVerificationCharge::sole()->reason,
        );
    }

    public function test_the_same_nin_submitted_twice_is_only_debited_once(): void
    {
        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->verifyNin($user);
        $this->verifyNin($user);

        $this->assertCount(1, $this->chargeLedgerFor($user));
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);
    }

    public function test_disabling_nin_charges_stops_the_deduction(): void
    {
        VerificationSetting::put('failed_charge_nin_enabled', false);

        $user = $this->user();
        $this->routeNinProvider();

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->verifyNin($user);

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertSame(0, FailedVerificationCharge::count());
    }

    public function test_the_nin_and_bvn_toggles_are_independent(): void
    {
        VerificationSetting::put('failed_charge_nin_enabled', false);

        $user = $this->user();
        $this->routeNinProvider();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->verifyNin($user);
        $this->bvnSearch()->search($user->fresh(), '12345678901', 'premium');

        // Only the BVN side billed.
        $this->assertCount(1, $this->chargeLedgerFor($user));
        $this->assertSame('bvn_verification_failed', $this->chargeLedgerFor($user)->first()->fundingtype);
        $this->assertSame(self::START_BALANCE - 50, (float) $user->fresh()->balance);
    }

    // ----------------------------------------------------------- balances ---

    public function test_the_wallet_arithmetic_balances(): void
    {
        $user = $this->user();
        $this->routeProviderFor(['bvn.verify']);

        Http::fake(['provider.test/*' => Http::response(['status' => false, 'message' => 'Not found'], 200)]);

        $this->bvnSearch()->search($user, '12345678901', 'premium');

        $charge = FailedVerificationCharge::sole();

        $this->assertSame($charge->balance_before - $charge->amount, $charge->balance_after);
        $this->assertSame($charge->balance_after, (float) $user->fresh()->balance);

        $ledger = $this->chargeLedgerFor($user)->sole();
        $this->assertSame($charge->balance_before, (float) $ledger->oldbal);
        $this->assertSame($charge->balance_after, (float) $ledger->newbal);
    }

    // ------------------------------------------------------------- admin ----

    public function test_an_admin_can_change_the_charges(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.verification-routing.failed-charges.update'), [
                'bvn_enabled' => true,
                'bvn_amount' => 75.5,
                'nin_enabled' => false,
                'nin_amount' => 0,
                'dedupe_seconds' => 30,
            ])
            ->assertSessionHas('success');

        VerificationSetting::flushCache();

        $this->assertSame(75.5, VerificationSetting::float('failed_charge_bvn_amount'));
        $this->assertTrue(VerificationSetting::bool('failed_charge_bvn_enabled'));
        $this->assertFalse(VerificationSetting::bool('failed_charge_nin_enabled'));
        $this->assertSame(30, VerificationSetting::int('failed_charge_dedupe_seconds'));
    }

    public function test_a_negative_charge_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.verification-routing.failed-charges.update'), [
                'bvn_enabled' => true,
                'bvn_amount' => -10,
                'nin_enabled' => true,
                'nin_amount' => 50,
                'dedupe_seconds' => 60,
            ])
            ->assertSessionHasErrors('bvn_amount');
    }

    public function test_a_non_admin_cannot_change_the_charges(): void
    {
        $this->actingAs($this->user())
            ->put(route('admin.verification-routing.failed-charges.update'), [
                'bvn_enabled' => true,
                'bvn_amount' => 999,
                'nin_enabled' => true,
                'nin_amount' => 999,
                'dedupe_seconds' => 60,
            ])
            ->assertRedirect(route('dashboard'));

        VerificationSetting::flushCache();
        $this->assertSame(50.0, VerificationSetting::float('failed_charge_bvn_amount'));
    }
}
