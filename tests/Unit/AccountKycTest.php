<?php

namespace Tests\Unit;

use App\Models\AccountKyc;
use PHPUnit\Framework\TestCase;

class AccountKycTest extends TestCase
{
    private function kyc(array $attributes = []): AccountKyc
    {
        return new AccountKyc($attributes + [
            'palmpay' => '1111111111',      // Billstack (retired)
            'palmpay2' => '2222222222',     // PayVessel
            'Ninesp' => '3333333333',       // PayVessel
            'moniepoint' => '0',
            'name' => 'Legacy Name',
            'palmpay2_name' => 'PV Name',
            'ninesp_name' => '9PSB Name',
        ]);
    }

    public function test_only_current_provider_accounts_are_shown_to_users(): void
    {
        $numbers = array_column($this->kyc()->toFormattedAccounts(), 'account_number');

        $this->assertSame(['2222222222', '3333333333'], $numbers);
        $this->assertNotContains('1111111111', $numbers, 'Billstack accounts must not be advertised.');
    }

    public function test_legacy_accounts_are_still_retrievable_when_asked_for(): void
    {
        $numbers = array_column($this->kyc()->toFormattedAccounts(true), 'account_number');

        $this->assertContains('1111111111', $numbers);
    }

    public function test_placeholder_zero_columns_are_never_shown(): void
    {
        $numbers = array_column($this->kyc()->toFormattedAccounts(true), 'account_number');

        $this->assertNotContains('0', $numbers);
    }

    public function test_a_user_with_only_legacy_accounts_sees_none(): void
    {
        $kyc = new AccountKyc(['palmpay' => '1111111111', 'name' => 'Old User']);

        $this->assertSame([], $kyc->toFormattedAccounts());
    }
}
