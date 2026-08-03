<?php

namespace Tests\Feature;

use App\Models\BvnModification;
use App\Models\BvnRetrieval;
use App\Models\BvnSdkForm;
use App\Models\Ipe;
use App\Models\NinDetail;
use App\Models\User;
use App\Models\Validation;
use App\Models\WalletEntry;
use App\Models\WalletHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The history screens, and the separation they depend on.
 *
 * The load-bearing behaviour here is that NIN Verification and NIN Validation
 * never show each other's rows. They share the `validation` table, and before
 * `validation.service` existed nothing told them apart — so the Verification
 * page listed validations, the Validation page listed lookups, and a user
 * could not tell what they had been charged for.
 */
class HistoryPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(float $balance = 5000): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => $balance])->save();

        return $user;
    }

    /* ==================================================================
     | NIN
     | ================================================================== */

    public function test_nin_verify_tab_lists_lookups_and_never_validations(): void
    {
        $user = $this->user();

        Validation::create([
            'nin' => '11111111111', 'status' => 'completed', 'result' => '{}',
            'comment' => 'NIN verify (nin) [11111111111]', 'oldBal' => 5000, 'newBal' => 4900,
            'service' => 'nin.verify', 'userId' => $user->id,
        ]);
        Validation::create([
            'nin' => '22222222222', 'status' => 'processing', 'result' => 'Pending',
            'comment' => 'Submitted to Provider — awaiting result', 'oldBal' => 4900, 'newBal' => 4750,
            'price' => 150, 'service' => 'nin.validation', 'userId' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.nin', ['tab' => 'verify']));

        $response->assertOk();
        $records = $response->viewData('page')['props']['records']['data'];

        $this->assertCount(1, $records);
        $this->assertSame('11111111111', $records[0]['identity']);
        // The lookup's fee is the balance movement, not a stored price column.
        $this->assertSame(100.0, $records[0]['amount']);

        // And the total spent counts only lookups, not the validation's ₦150.
        $stats = collect($response->viewData('page')['props']['stats'])->keyBy('label');
        $this->assertSame(100.0, (float) $stats['Total spent']['value']);
    }

    public function test_nin_validation_tab_lists_filed_jobs_and_never_lookups(): void
    {
        $user = $this->user();

        Validation::create([
            'nin' => '11111111111', 'status' => 'completed', 'result' => '{}',
            'comment' => 'NIN verify (nin) [11111111111]', 'oldBal' => 5000, 'newBal' => 4900,
            'service' => 'nin.verify', 'userId' => $user->id,
        ]);
        Validation::create([
            'nin' => '22222222222', 'status' => 'processing', 'result' => 'Pending',
            'comment' => 'Submitted to Provider — awaiting result', 'oldBal' => 4900, 'newBal' => 4750,
            'price' => 150, 'providerRef' => 'UP-9', 'service' => 'nin.validation', 'userId' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.nin', ['tab' => 'validation']));

        $response->assertOk();
        $records = $response->viewData('page')['props']['records']['data'];

        $this->assertCount(1, $records);
        $this->assertSame('22222222222', $records[0]['identity']);
        $this->assertSame(150.0, $records[0]['amount']);
        $this->assertSame('UP-9', $records[0]['provider_reference']);
    }

    public function test_ipe_tab_lists_clearances_keyed_on_the_tracking_id(): void
    {
        $user = $this->user();

        Ipe::create([
            'trkid' => 'ABC123DEF456GHI', 'status' => 'processing', 'result' => 'Pending',
            'comment' => 'Submitted', 'oldBal' => 5000, 'newBal' => 4800, 'price' => 200,
            'userId' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.nin', ['tab' => 'ipe']));

        $response->assertOk();
        $records = $response->viewData('page')['props']['records']['data'];

        $this->assertCount(1, $records);
        $this->assertSame('ABC123DEF456GHI', $records[0]['identity']);
        $this->assertSame('Tracking ID', $records[0]['identity_label']);
    }

    public function test_nin_history_only_shows_the_signed_in_users_records(): void
    {
        $user = $this->user();
        $other = $this->user();

        Validation::create([
            'nin' => '33333333333', 'status' => 'completed', 'result' => '{}',
            'comment' => 'someone else', 'oldBal' => 100, 'newBal' => 0,
            'service' => 'nin.verify', 'userId' => $other->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.nin'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['records']['data']);
    }

    public function test_an_unknown_tab_falls_back_to_the_first_one(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get(route('history.nin', ['tab' => 'nonsense']));

        $response->assertOk();
        $this->assertSame('verify', $response->viewData('page')['props']['tab']);
    }

    /* ==================================================================
     | BVN
     | ================================================================== */

    public function test_bvn_tabs_each_read_their_own_service(): void
    {
        $user = $this->user();

        NinDetail::create([
            'id' => 'BVN-1', 'surname' => 'Bello', 'othernames' => 'Amina', 'idtype' => 'bvn',
            'idvalue' => '12345678901', 'sliptype' => 'premium', 'status' => 'success',
            'oldBal' => 5000, 'newBal' => 4850, 'price' => 150, 'userId' => $user->id,
        ]);
        // A NIN lookup lives in the same table and must not appear here.
        NinDetail::create([
            'id' => 'NIN-1', 'idtype' => 'nin', 'idvalue' => '99999999999', 'sliptype' => 'verification',
            'status' => 'success', 'oldBal' => 4850, 'newBal' => 4750, 'price' => 100, 'userId' => $user->id,
        ]);

        BvnModification::create([
            'bvn' => '12345678901', 'nin' => '99999999999', 'ninSlipUrl' => '', 'ninSlipImage' => '',
            'serviceType' => 'modify-name', 'status' => 'pending', 'amountCharged' => '2500',
            'oldBal' => '4750', 'newBal' => '2250', 'userId' => $user->id,
        ]);

        BvnSdkForm::create([
            'agentLocation' => 'Kano', 'agentBvn' => '12345678901', 'bankName' => 'GTB',
            'accountNumber' => '0123456789', 'accountName' => 'A Bello', 'firstName' => 'Amina',
            'lastName' => 'Bello', 'email' => 'amina@example.com', 'phoneNumber' => '08030000000',
            'address' => '1 Road', 'stateOfResidence' => 'Kano', 'dateOfBirth' => now()->subYears(30),
            'lga' => 'Nassarawa', 'zone' => 'NW', 'oldBal' => '2250', 'newBal' => '1250',
            'userId' => $user->id,
        ]);

        BvnRetrieval::create([
            'firstname' => 'Amina', 'surname' => 'Bello', 'ticketId1' => '87654321',
            'oldBal' => '1250', 'newBal' => '1000', 'userId' => $user->id,
        ]);

        $verify = $this->actingAs($user)->get(route('history.bvn', ['tab' => 'verify']));
        $verify->assertOk();
        $records = $verify->viewData('page')['props']['records']['data'];
        $this->assertCount(1, $records);
        $this->assertSame('12345678901', $records[0]['identity']);

        foreach (['modification', 'onboarding', 'retrieval'] as $tab) {
            $response = $this->actingAs($user)->get(route('history.bvn', ['tab' => $tab]));
            $response->assertOk();
            $this->assertCount(1, $response->viewData('page')['props']['records']['data'], "tab {$tab}");
        }
    }

    public function test_bvn_modification_spend_sums_a_string_money_column(): void
    {
        $user = $this->user();

        foreach (['2500', '1500'] as $amount) {
            BvnModification::create([
                'bvn' => '12345678901', 'nin' => '99999999999', 'ninSlipUrl' => '', 'ninSlipImage' => '',
                'serviceType' => 'modify-name', 'status' => 'completed', 'amountCharged' => $amount,
                'oldBal' => '5000', 'newBal' => '2500', 'userId' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->get(route('history.bvn', ['tab' => 'modification']));

        $response->assertOk();
        $stats = collect($response->viewData('page')['props']['stats'])->keyBy('label');
        $this->assertSame(4000.0, $stats['Total spent']['value']);
    }

    /* ==================================================================
     | Wallet
     | ================================================================== */

    public function test_funding_tab_excludes_refunds(): void
    {
        $user = $this->user();

        WalletHistory::create([
            'type' => 'credit', 'status' => 'success', 'fundingtype' => 'automatic-funding',
            'amount' => 5000, 'oldbal' => 0, 'newbal' => 5000, 'userId' => $user->id,
        ]);
        WalletHistory::create([
            'type' => 'credit', 'status' => 'refund', 'fundingtype' => 'refund',
            'amount' => 150, 'oldbal' => 5000, 'newbal' => 5150, 'userId' => $user->id,
        ]);
        WalletHistory::create([
            'type' => 'debit', 'status' => 'success', 'fundingtype' => 'nin_verification',
            'amount' => 100, 'oldbal' => 5150, 'newbal' => 5050, 'userId' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.wallet', ['tab' => 'funding']));

        $response->assertOk();
        $records = $response->viewData('page')['props']['records']['data'];

        $this->assertCount(1, $records);
        $this->assertSame(5000.0, $records[0]['amount']);
        $this->assertSame('Bank transfer', $records[0]['source']);
    }

    public function test_refunds_tab_unions_both_ledgers(): void
    {
        $user = $this->user();

        // A reversed service charge.
        WalletHistory::create([
            'type' => 'credit', 'status' => 'refund', 'fundingtype' => 'refund',
            'amount' => 150, 'oldbal' => 5000, 'newbal' => 5150, 'userId' => $user->id,
        ]);
        // A data purchase the vendor failed, refunded through the data ledger —
        // which writes nowhere near wallethistory.
        WalletEntry::create([
            'user_id' => $user->id, 'direction' => 'credit', 'amount' => 300,
            'balance_after' => 5450, 'reason' => 'refund', 'data_transaction_id' => 'DT-1',
        ]);
        // Not a refund: must not be counted.
        WalletEntry::create([
            'user_id' => $user->id, 'direction' => 'debit', 'amount' => 300,
            'balance_after' => 5150, 'reason' => 'purchase', 'data_transaction_id' => 'DT-1',
        ]);

        $response = $this->actingAs($user)->get(route('history.wallet', ['tab' => 'refunds']));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $records = collect($props['records']['data']);

        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(
            ['Service refund', 'Data purchase'],
            $records->pluck('source')->all(),
        );

        $stats = collect($props['stats'])->keyBy('label');
        $this->assertSame(450.0, (float) $stats['Total refunded']['value']);

        // The data ledger stores only the closing balance; the opening one is
        // derived so both sources render the same before/after pair.
        $dataRefund = $records->firstWhere('source', 'Data purchase');
        $this->assertSame(5150.0, $dataRefund['old_balance']);
        $this->assertSame(5450.0, $dataRefund['new_balance']);
    }

    public function test_refunds_are_scoped_to_the_signed_in_user(): void
    {
        $user = $this->user();
        $other = $this->user();

        WalletEntry::create([
            'user_id' => $other->id, 'direction' => 'credit', 'amount' => 999,
            'balance_after' => 999, 'reason' => 'refund',
        ]);
        WalletHistory::create([
            'type' => 'credit', 'status' => 'refund', 'fundingtype' => 'refund',
            'amount' => 888, 'oldbal' => 0, 'newbal' => 888, 'userId' => $other->id,
        ]);

        $response = $this->actingAs($user)->get(route('history.wallet', ['tab' => 'refunds']));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['records']['data']);
    }

    public function test_history_requires_authentication(): void
    {
        $this->get(route('history.nin'))->assertRedirect(route('login'));
        $this->get(route('history.bvn'))->assertRedirect(route('login'));
        $this->get(route('history.wallet'))->assertRedirect(route('login'));
    }
}
