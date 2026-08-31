<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BvnRetrieval;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BVN retrieval is charged on submission and fulfilled by staff days later, so
 * the fee has to be returnable by hand when the BVN cannot be found.
 *
 * What is pinned here is the part that is easy to get wrong on a screen where
 * several admins work the same queue over several days: the refund is once-only,
 * it is capped at what that row was actually charged, and changing a request's
 * status is not itself a refund.
 */
class BvnRetrievalRefundTest extends TestCase
{
    use RefreshDatabase;

    private const FEE = 500.0;

    public function test_an_admin_refund_credits_the_wallet_once(): void
    {
        [$user, $retrieval] = $this->submittedRetrieval();
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-retrievals.refund', $retrieval->id), [
                'amount' => self::FEE,
                'reason' => 'BVN could not be retrieved for this BMS ID',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $retrieval->fresh();

        $this->assertSame($balance + self::FEE, (float) $user->fresh()->balance);
        $this->assertNotNull($fresh->refundedAt);
        $this->assertSame(self::FEE, (float) $fresh->refundAmount);
        $this->assertSame('Refunded: BVN could not be retrieved for this BMS ID', $fresh->comment);
        $this->assertFalse($fresh->isRefundable());

        // Second attempt, days later, by another admin.
        $this->actingAs($this->admin())
            ->post(route('admin.bvn-retrievals.refund', $retrieval->id), ['amount' => self::FEE])
            ->assertSessionHasErrors('message');

        $this->assertSame($balance + self::FEE, (float) $user->fresh()->balance);
    }

    public function test_a_partial_refund_is_allowed_but_capped_at_what_was_charged(): void
    {
        [$user, $retrieval] = $this->submittedRetrieval();
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-retrievals.refund', $retrieval->id), ['amount' => self::FEE + 1])
            ->assertSessionHasErrors('amount');

        $this->assertNull($retrieval->fresh()->refundedAt);
        $this->assertSame($balance, (float) $user->fresh()->balance);

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-retrievals.refund', $retrieval->id), ['amount' => 200])
            ->assertSessionHasNoErrors();

        $this->assertSame($balance + 200, (float) $user->fresh()->balance);
        $this->assertSame(200.0, (float) $retrieval->fresh()->refundAmount);
    }

    public function test_rejecting_a_request_does_not_move_money(): void
    {
        [$user, $retrieval] = $this->submittedRetrieval();
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($this->admin())
            ->patch(route('admin.bvn-retrievals.update', $retrieval->id), [
                'status' => 'rejected',
                'comment' => 'No record found',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $retrieval->fresh();

        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($balance, (float) $user->fresh()->balance);
        $this->assertNull($fresh->refundedAt);
        // Still owed, so the button stays live.
        $this->assertTrue($fresh->isRefundable());
    }

    public function test_a_request_that_was_never_charged_cannot_be_refunded(): void
    {
        [$user] = $this->submittedRetrieval();

        $free = BvnRetrieval::create([
            'firstname' => '-', 'surname' => '-', 'phone' => '-',
            'retrievalType' => 'id', 'bvn' => '', 'ticketId1' => '',
            'ticketId2' => '87654321', 'batchId' => '', 'nin' => '',
            'status' => 'pending', 'userId' => $user->id,
            'oldBal' => '5000', 'newBal' => '5000',
        ]);

        $this->assertFalse($free->isRefundable());

        $this->actingAs($this->admin())
            ->post(route('admin.bvn-retrievals.refund', $free->id), ['amount' => 100])
            ->assertSessionHasErrors();
    }

    // --------------------------------------------------------------- helpers

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::ADMIN])->save();

        return $admin;
    }

    /**
     * A user who has submitted one retrieval request through the real user-side
     * controller, so the balance delta on the row is the one the refund reads.
     *
     * @return array{0: User, 1: BvnRetrieval}
     */
    private function submittedRetrieval(): array
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => 5000])->save();

        ServicePrice::updateOrCreate(
            ['service' => 'bvn.retrieve.id', 'role' => ServicePrice::BASE],
            ['price' => self::FEE, 'is_active' => true],
        );
        ServicePrice::forgetCache();

        $this->actingAs($user)
            ->post(route('bvn-retrieval.store'), ['bmsId' => '12345678'])
            ->assertSessionHasNoErrors();

        $retrieval = BvnRetrieval::where('userId', $user->id)->firstOrFail();

        $this->assertSame(self::FEE, $retrieval->chargedAmount());

        return [$user, $retrieval];
    }
}
