<?php

namespace Tests\Feature\Wallet;

use App\Models\AccountKyc;
use App\Models\FundingSetting;
use App\Models\UnattributedPayment;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The webhook credits real money, and the version this was ported from had both
 * its signature and IP checks commented out. These tests exist mainly to make
 * sure that cannot happen again unnoticed.
 */
class PayVesselWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payvessel.secret', self::SECRET);
        // The test client reports 127.0.0.1; allow it so signature behaviour can
        // be tested independently of the IP check.
        config()->set('services.payvessel.webhook_ips', ['127.0.0.1']);
    }

    private function payload(string $reference, float $amount, string $accountNumber, float $settlement = 0): array
    {
        return [
            'order' => [
                'amount' => (string) $amount,
                'settlement_amount' => (string) ($settlement ?: $amount),
                'fee' => '10',
                'description' => 'Wallet funding',
                'account_number' => $accountNumber,
            ],
            'transaction' => ['reference' => $reference],
            'customer' => ['email' => 'agent@example.com', 'name' => 'JOHN DOE'],
        ];
    }

    private function postSigned(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            '/api/webhooks/payvessel',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYVESSEL_HTTP_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, self::SECRET),
            ],
            $body
        );
    }

    private function userWithAccount(string $accountNumber = '5030200545'): User
    {
        $user = User::factory()->create(['email' => 'agent@example.com']);

        AccountKyc::create([
            'id' => $user->id,
            'userId' => $user->id,
            'bvn' => '22345678901',
            'palmpay2' => $accountNumber,
            'palmpay2_name' => 'ABC-JOHN DOE',
            'status' => 'generated',
        ]);

        return $user;
    }

    public function test_a_signed_payment_credits_the_wallet(): void
    {
        $user = $this->userWithAccount();

        $this->postSigned($this->payload('PV-REF-1', 1000, '5030200545'))
            ->assertOk();

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        $this->assertNotNull(WalletHistory::whereKey('PV-REF-1')->first());
    }

    public function test_an_unsigned_request_credits_nothing(): void
    {
        $user = $this->userWithAccount();

        $response = $this->call(
            'POST',
            '/api/webhooks/payvessel',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($this->payload('PV-REF-2', 5000, '5030200545'))
        );

        $response->assertStatus(400);
        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    public function test_a_forged_signature_credits_nothing(): void
    {
        $user = $this->userWithAccount();

        $this->postSigned($this->payload('PV-REF-3', 5000, '5030200545'), 'not-the-real-signature')
            ->assertStatus(400);

        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    /**
     * The signature covers the raw body, so a tampered amount must not verify
     * even though the rest of the payload is untouched.
     */
    public function test_a_tampered_amount_credits_nothing(): void
    {
        $user = $this->userWithAccount();

        $original = $this->payload('PV-REF-4', 100, '5030200545');
        $signature = hash_hmac('sha512', json_encode($original), self::SECRET);

        $tampered = $original;
        $tampered['order']['amount'] = '999999';

        $this->postSigned($tampered, $signature)->assertStatus(400);

        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    public function test_a_request_from_an_unlisted_address_is_rejected(): void
    {
        config()->set('services.payvessel.webhook_ips', ['3.255.23.38']);

        $user = $this->userWithAccount();

        $this->postSigned($this->payload('PV-REF-5', 1000, '5030200545'))
            ->assertStatus(403);

        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    public function test_a_repeated_delivery_credits_once(): void
    {
        $user = $this->userWithAccount();
        $payload = $this->payload('PV-REF-6', 2500, '5030200545');

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $this->assertSame(2500.0, (float) $user->fresh()->balance);
        $this->assertSame(1, WalletHistory::where('userId', $user->id)->count());
    }

    public function test_gross_amount_is_credited_by_default(): void
    {
        $user = $this->userWithAccount();

        $this->postSigned($this->payload('PV-REF-7', 1000, '5030200545', settlement: 985))
            ->assertOk();

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_settlement_amount_is_credited_when_admin_enables_it(): void
    {
        FundingSetting::current()->update(['credit_net_of_fees' => true]);

        $user = $this->userWithAccount();

        $this->postSigned($this->payload('PV-REF-8', 1000, '5030200545', settlement: 985))
            ->assertOk();

        $this->assertSame(985.0, (float) $user->fresh()->balance);
    }

    /**
     * The account number is the strong identifier -- it was issued by us and
     * belongs to exactly one user. Email is a fallback only, so if the payload
     * carries someone else's address the money must still follow the account.
     */
    public function test_payment_follows_the_account_number_not_the_email(): void
    {
        $owner = $this->userWithAccount('5030200545');
        $other = User::factory()->create(['email' => 'someone-else@example.com']);

        $payload = $this->payload('PV-REF-9', 700, '5030200545');
        $payload['customer']['email'] = 'someone-else@example.com';

        $this->postSigned($payload)->assertOk();

        $this->assertSame(700.0, (float) $owner->fresh()->balance);
        $this->assertSame(0.0, (float) $other->fresh()->balance);
    }

    /**
     * With no account number on the payload, email is all we have.
     */
    public function test_payment_falls_back_to_email_when_no_account_number(): void
    {
        $user = $this->userWithAccount();

        $payload = $this->payload('PV-REF-11', 300, '5030200545');
        unset($payload['order']['account_number']);

        $this->postSigned($payload)->assertOk();

        $this->assertSame(300.0, (float) $user->fresh()->balance);
    }

    public function test_an_unattributable_payment_credits_nobody(): void
    {
        $user = $this->userWithAccount();

        $payload = $this->payload('PV-REF-10', 1000, '9999999999');
        unset($payload['customer']);

        $this->postSigned($payload)->assertOk();

        $this->assertSame(0.0, (float) $user->fresh()->balance);
        $this->assertNull(WalletHistory::whereKey('PV-REF-10')->first());

        // ...but it is not lost: it lands in the reconciliation queue, because
        // we answer 200 and PayVessel will never retry it.
        $this->assertDatabaseHas('unattributed_payments', [
            'reference' => 'PV-REF-10',
            'provider' => 'payvessel',
            'account_number' => '9999999999',
            'status' => UnattributedPayment::STATUS_PENDING,
        ]);
    }

    public function test_a_redelivered_unattributable_payment_is_recorded_once(): void
    {
        $this->userWithAccount();

        $payload = $this->payload('PV-REF-11', 1000, '9999999999');
        unset($payload['customer']);

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $this->assertSame(1, UnattributedPayment::where('reference', 'PV-REF-11')->count());
    }

    public function test_a_redelivery_does_not_undo_an_admin_decision_to_ignore(): void
    {
        $this->userWithAccount();

        $payload = $this->payload('PV-REF-12', 1000, '9999999999');
        unset($payload['customer']);

        $this->postSigned($payload)->assertOk();

        UnattributedPayment::where('reference', 'PV-REF-12')
            ->update(['status' => UnattributedPayment::STATUS_IGNORED]);

        $this->postSigned($payload)->assertOk();

        $this->assertSame(
            UnattributedPayment::STATUS_IGNORED,
            UnattributedPayment::where('reference', 'PV-REF-12')->first()->status,
        );
    }
}
