<?php

namespace App\Support;

/**
 * Nigerian bank codes as they appear in BVN enrolment records.
 *
 * BVN providers report the enrolling bank as a CBN institution code ("011"),
 * which is meaningless on a printed slip and to an API integrator alike. This
 * turns it into the bank's name once, server-side, so the slip components, the
 * search screen and the reseller API all show the same thing -- rather than
 * every consumer shipping its own copy of the table and drifting apart.
 */
class BankDirectory
{
    /**
     * What a code that is not in the table becomes.
     *
     * These are real enrolments taken by licensed agents rather than a bank
     * branch, so the code genuinely has no bank behind it. Showing the raw
     * number would read as a data error.
     */
    public const UNKNOWN = 'Agency enrollment';

    /** CBN institution code => bank name. */
    public const BANKS = [
        '011' => 'First Bank of Nigeria Plc',
        '014' => 'Mainstreet Bank Plc',
        '032' => 'Union Bank Nigeria Plc',
        '033' => 'United Bank for Africa Plc',
        '035' => 'WEMA Bank Plc',
        '039' => 'Stanbic IBTC Plc',
        '044' => 'Access Bank Nigeria Plc Or Diamond Bank Plc',
        '050' => 'Ecobank Nigeria',
        '057' => 'Zenith Bank International',
        '058' => 'Guaranty Trust Bank Plc',
        '067' => 'Polaris Bank',
        '068' => 'Standard Chartered Bank',
        '070' => 'Fidelity Bank Plc',
        '076' => 'Skye Bank Plc',
        '082' => 'Keystone Bank Ltd',
        '084' => 'Enterprise Bank Plc',
        '100' => 'Suntrust Bank',
        '101' => 'Providus Bank',
        '102' => 'TITAN TRUST BANK',
        '103' => 'GLOBUS BANK',
        '104' => 'PARALLEX BANK LIMITED',
        '105' => 'PREMIUM TRUST BANK LTD',
        '106' => 'SIGNATURE BANK LTD',
        '107' => 'OPTIMUS BANK LTD',
        '214' => 'First City Monument Bank',
        '215' => 'Unity Bank Plc',
        '232' => 'Sterling Bank Plc',
        '301' => 'Jaiz Bank',
        '303' => 'LOTUS BANK LIMITED',
    ];

    /**
     * Resolve whatever the provider reported into a bank name.
     *
     * Only numeric values are treated as codes. A provider that already sends
     * the name ("FIRST BANK PLC") is left alone -- rewriting that to
     * "Agency enrollment" would throw away the answer we were after.
     */
    public static function name(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value)) {
            return $value;
        }

        // Codes are three digits; providers are inconsistent about the leading
        // zero, so "11", "011" and 11 all resolve to the same bank.
        return self::BANKS[str_pad($value, 3, '0', STR_PAD_LEFT)] ?? self::UNKNOWN;
    }
}
