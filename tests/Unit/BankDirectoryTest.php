<?php

namespace Tests\Unit;

use App\Support\BankDirectory;
use PHPUnit\Framework\TestCase;

/**
 * BVN enrolment records name the bank by CBN code. Resolving it is what keeps
 * "011" off a printed slip.
 */
class BankDirectoryTest extends TestCase
{
    public function test_it_resolves_a_code_to_the_bank_name(): void
    {
        $this->assertSame('First Bank of Nigeria Plc', BankDirectory::name('011'));
        $this->assertSame('Guaranty Trust Bank Plc', BankDirectory::name('058'));
        $this->assertSame('Jaiz Bank', BankDirectory::name('301'));
    }

    /**
     * Providers are inconsistent about the leading zero, and JSON numbers lose
     * it altogether, so 11 and "011" have to be the same bank.
     */
    public function test_it_tolerates_a_missing_leading_zero(): void
    {
        $this->assertSame('First Bank of Nigeria Plc', BankDirectory::name('11'));
        $this->assertSame('First Bank of Nigeria Plc', BankDirectory::name(11));
        $this->assertSame('Access Bank Nigeria Plc Or Diamond Bank Plc', BankDirectory::name(44));
    }

    public function test_an_unlisted_code_is_an_agency_enrollment(): void
    {
        $this->assertSame('Agency enrollment', BankDirectory::name('999'));
        $this->assertSame('Agency enrollment', BankDirectory::name('000'));
    }

    /**
     * A provider that already sends the name must not have it replaced by
     * "Agency enrollment" -- that would discard the answer.
     */
    public function test_a_name_is_passed_through_untouched(): void
    {
        $this->assertSame('FIRST BANK PLC', BankDirectory::name('FIRST BANK PLC'));
        $this->assertSame('Zenith Bank', BankDirectory::name('  Zenith Bank  '));
    }

    public function test_nothing_stays_nothing(): void
    {
        $this->assertNull(BankDirectory::name(null));
        $this->assertNull(BankDirectory::name(''));
        $this->assertNull(BankDirectory::name('   '));
    }
}
