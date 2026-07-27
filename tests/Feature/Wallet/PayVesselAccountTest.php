<?php

namespace Tests\Feature\Wallet;

use App\Models\AccountKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayVesselAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payvessel.key', 'test-key');
        config()->set('services.payvessel.secret', 'test-secret');
        config()->set('services.payvessel.business_id', 'BIZ123');
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            '*customerReservedAccount*' => Http::response([
                'status' => true,
                'service' => 'CREATE_VIRTUAL_ACCOUNT',
                'business' => 'BIZ123',
                'banks' => [
                    [
                        // Note the capital P: nimcweb matched "Palmpay" and so
                        // dropped every PalmPay account it was ever sent.
                        'bankName' => 'PalmPay',
                        'accountNumber' => '6030200545',
                        'accountName' => 'ABC-JOHN DOE',
                        'trackingReference' => 'TRK123',
                    ],
                    [
                        'bankName' => '9Payment Service Bank',
                        'accountNumber' => '5030200545',
                        'accountName' => 'ABC-JOHN DOE',
                        'trackingReference' => 'TRK124',
                    ],
                ],
            ]),
        ]);
    }

    public function test_it_creates_and_stores_both_accounts(): void
    {
        $this->fakeSuccess();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/wallet/virtual-account', ['bvn' => '22345678901'])
            ->assertSessionHasNoErrors();

        $kyc = AccountKyc::where('userId', $user->id)->firstOrFail();

        $this->assertSame('6030200545', $kyc->palmpay2);
        $this->assertSame('5030200545', $kyc->Ninesp);
        $this->assertSame('TRK123', $kyc->payvessel_id);
        $this->assertSame('generated', $kyc->status);

        Http::assertSent(function ($request) use ($user) {
            return $request->hasHeader('api-key', 'test-key')
                && $request->hasHeader('api-secret', 'test-secret')
                && $request['account_type'] === 'STATIC'
                && $request['businessid'] === 'BIZ123'
                && $request['bvn'] === '22345678901'
                && $request['email'] === $user->email;
        });
    }

    public function test_both_accounts_are_visible_to_the_user(): void
    {
        $this->fakeSuccess();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/wallet/virtual-account', ['bvn' => '22345678901']);

        $accounts = $user->fresh()->reservedAccounts();

        $this->assertCount(2, $accounts);
        $this->assertEqualsCanonicalizing(
            ['6030200545', '5030200545'],
            array_column($accounts, 'account_number'),
        );
    }

    public function test_a_rejection_is_reported_to_the_user(): void
    {
        Http::fake([
            '*customerReservedAccount*' => Http::response([
                'status' => false,
                'message' => 'Invalid BVN supplied',
            ], 400),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/wallet/virtual-account', ['bvn' => '22345678901'])
            ->assertSessionHasErrors('bvn');

        $this->assertNull(AccountKyc::where('userId', $user->id)->first());
    }

    /**
     * accountkyc.bvn is UNIQUE. Before this was checked, submitting a BVN held
     * by another row threw UniqueConstraintViolationException out of the
     * controller as a 500 -- after PayVessel had already issued live accounts.
     */
    public function test_a_bvn_held_by_another_account_is_refused_without_calling_out(): void
    {
        Http::fake();

        $other = User::factory()->create();
        AccountKyc::create([
            'id' => $other->id,
            'userId' => $other->id,
            'bvn' => '22345678901',
            'status' => 'generated',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/wallet/virtual-account', ['bvn' => '22345678901'])
            ->assertSessionHasErrors('bvn');

        Http::assertNothingSent();
        $this->assertNull(AccountKyc::where('userId', $user->id)->first());
    }

    /**
     * Re-running against an existing row must update it in place. The previous
     * version passed 'id' through updateOrCreate, so the UPDATE rewrote the
     * primary key -- orphaning anything that referenced it.
     */
    public function test_regenerating_keeps_the_existing_primary_key(): void
    {
        $this->fakeSuccess();

        $user = User::factory()->create();

        AccountKyc::create([
            'id' => 'existing-kyc-id',
            'userId' => $user->id,
            'bvn' => '22345678901',
            'status' => 'generated',
        ]);

        $this->actingAs($user)
            ->post('/wallet/virtual-account', ['bvn' => '22345678901'])
            ->assertSessionHasNoErrors();

        $kyc = AccountKyc::where('userId', $user->id)->firstOrFail();

        $this->assertSame('existing-kyc-id', $kyc->id);
        $this->assertSame('6030200545', $kyc->palmpay2);
        $this->assertSame(1, AccountKyc::where('userId', $user->id)->count());
    }

    public function test_an_unconfigured_provider_does_not_call_out(): void
    {
        config()->set('services.payvessel.key', null);
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/wallet/virtual-account', ['bvn' => '22345678901'])
            ->assertSessionHasErrors('bvn');

        Http::assertNothingSent();
    }
}
