<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BvnModification;
use App\Models\BvnSdkForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BVN Modification and BVN SDK Onboarding are charged on submission and worked
 * by staff, so — like BVN Retrieval — the fee must be returnable from the admin
 * list: once only, capped at what the row was charged, and never as a side
 * effect of changing status.
 */
class BvnModificationAndSdkRefundTest extends TestCase
{
    use RefreshDatabase;

    private const FEE = 1500.0;

    private const START_BALANCE = 5000.0;

    public function test_a_modification_refund_credits_the_wallet_once(): void
    {
        $user = $this->user(self::START_BALANCE - self::FEE);
        $mod = $this->modification($user);

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-modifications.refund', $mod->id), [
                'amount' => self::FEE,
                'reason' => 'Record could not be modified',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $mod->fresh();

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertNotNull($fresh->refundedAt);
        $this->assertSame(self::FEE, (float) $fresh->refundAmount);
        $this->assertSame('Refunded: Record could not be modified', $fresh->comment);
        $this->assertFalse($fresh->isRefundable());

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-modifications.refund', $mod->id), ['amount' => self::FEE])
            ->assertSessionHasErrors('message');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
    }

    public function test_a_modification_refund_is_capped_at_amount_charged(): void
    {
        $user = $this->user(self::START_BALANCE - self::FEE);
        $mod = $this->modification($user);

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-modifications.refund', $mod->id), ['amount' => self::FEE + 1])
            ->assertSessionHasErrors('amount');

        $this->assertNull($mod->fresh()->refundedAt);

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-modifications.refund', $mod->id), ['amount' => 500])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::START_BALANCE - self::FEE + 500, (float) $user->fresh()->balance);
    }

    public function test_a_legacy_modification_without_amount_charged_falls_back_to_balance_delta(): void
    {
        $user = $this->user(0);
        $mod = $this->modification($user, ['amountCharged' => null]);

        $this->assertSame(self::FEE, $mod->chargedAmount());
    }

    public function test_rejecting_a_modification_does_not_move_money(): void
    {
        $user = $this->user(self::START_BALANCE - self::FEE);
        $mod = $this->modification($user);

        $this->actingAs($this->admin())
            ->patch(route('admin.bvn-modifications.status', $mod->id), [
                'status' => 'rejected',
                'comment' => 'Mismatched NIN slip',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::START_BALANCE - self::FEE, (float) $user->fresh()->balance);
        $this->assertTrue($mod->fresh()->isRefundable());
    }

    public function test_an_sdk_form_refund_credits_the_wallet_once(): void
    {
        $user = $this->user(self::START_BALANCE - self::FEE);
        $form = $this->sdkForm($user);

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-sdk-forms.refund', $form->id), ['amount' => self::FEE])
            ->assertSessionHasNoErrors();

        $fresh = $form->fresh();

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
        $this->assertNotNull($fresh->refundedAt);
        $this->assertFalse($fresh->isRefundable());

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-sdk-forms.refund', $form->id), ['amount' => self::FEE])
            ->assertSessionHasErrors('message');

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
    }

    public function test_an_sdk_form_that_was_never_charged_cannot_be_refunded(): void
    {
        $user = $this->user(self::START_BALANCE);
        $form = $this->sdkForm($user, ['oldBal' => '5000', 'newBal' => '5000']);

        $this->assertFalse($form->isRefundable());

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-sdk-forms.refund', $form->id), ['amount' => 100])
            ->assertSessionHasErrors();

        $this->assertSame(self::START_BALANCE, (float) $user->fresh()->balance);
    }

    public function test_the_admin_lists_carry_refund_state(): void
    {
        $user = $this->user(0);
        $this->modification($user);
        $this->sdkForm($user);

        $this->actingAs($this->admin())
            ->get(route('admin.bvn-modifications.index'))
            ->assertInertia(fn ($page) => $page
                ->where('requests.data.0.is_refundable', true)
                ->where('requests.data.0.charged_amount', fn ($v) => (float) $v === self::FEE));

        $this->actingAs($this->admin())
            ->get(route('admin.bvn-sdk-forms.index'))
            ->assertInertia(fn ($page) => $page
                ->where('forms.data.0.is_refundable', true)
                ->where('forms.data.0.charged_amount', fn ($v) => (float) $v === self::FEE));
    }

    public function test_non_admins_cannot_refund(): void
    {
        $user = $this->user(0);
        $mod = $this->modification($user);

        $this->actingAs($user)
            ->post(route('admin.bvn-modifications.refund', $mod->id), ['amount' => self::FEE]);

        $this->assertNull($mod->fresh()->refundedAt);
        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    // --------------------------------------------------------------- helpers

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::ADMIN])->save();

        return $admin;
    }

    private function user(float $balance): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => $balance])->save();

        return $user;
    }

    private function modification(User $user, array $overrides = []): BvnModification
    {
        return BvnModification::create($overrides + [
            'serviceType' => 'name_correction',
            'bvn' => '22222222222',
            'nin' => '11111111111',
            'ninSlipUrl' => 'nin-slip.png',
            'ninSlipImage' => 'x',
            'oldBal' => (string) self::START_BALANCE,
            'newBal' => (string) (self::START_BALANCE - self::FEE),
            'amountCharged' => (string) self::FEE,
            'status' => 'pending',
            'userId' => $user->id,
        ]);
    }

    private function sdkForm(User $user, array $overrides = []): BvnSdkForm
    {
        static $n = 0;
        $n++;

        return BvnSdkForm::create($overrides + [
            'agentLocation' => 'Kano',
            'agentBvn' => '22222222222',
            'bankName' => 'Test Bank',
            'accountNumber' => '0123456789',
            'accountName' => 'Test Agent',
            'firstName' => 'Test',
            'lastName' => 'Agent',
            'email' => "agent{$n}@example.com",
            'phoneNumber' => '0800000000'.$n,
            'address' => '1 Test Street',
            'stateOfResidence' => 'Kano',
            'dateOfBirth' => '1990-01-01',
            'lga' => 'Nassarawa',
            'zone' => 'north-west',
            'oldBal' => (string) self::START_BALANCE,
            'newBal' => (string) (self::START_BALANCE - self::FEE),
            'status' => 'pending',
            'userId' => $user->id,
        ]);
    }
}
