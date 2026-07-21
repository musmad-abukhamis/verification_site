<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Accepts the body shapes the common Nigerian data-vending APIs use and maps
 * them onto the fields BuyDataRequest validates.
 *
 * Integrators arrive already wired to another provider, so the same field turns
 * up as `phone`, `mobile_number` or `msisdn`, and the plan as `data_plan`,
 * `plan` or `plan_id`. Rather than making every one of them rewrite their
 * client, we accept the aliases and normalize here -- one place, so the rest of
 * the pipeline only ever sees canonical names.
 *
 * Runs for the web UI too, where it is a no-op: that form already posts
 * canonical names and lowercase network slugs.
 */
class DataRequestNormalizer
{
    /**
     * Numeric network ids, the de-facto standard across these APIs.
     */
    public const NETWORK_IDS = [
        1 => 'mtn',
        2 => 'airtel',
        3 => 'glo',
        4 => '9mobile',
    ];

    /**
     * canonical field => accepted aliases, in precedence order.
     *
     * Note `request-id` with a hyphen and `Ported_number` with a capital P:
     * both are real spellings in live provider docs, not typos.
     */
    private const ALIASES = [
        'network' => ['network', 'network_id', 'networkId', 'networkID', 'operator', 'service'],
        'plan_id' => ['plan_id', 'data_plan', 'dataplan', 'plan', 'planId', 'plan_code', 'variation_code'],
        'phone' => ['phone', 'mobile_number', 'phone_number', 'phoneNumber', 'msisdn', 'mobile', 'recipient'],
        'ported' => ['ported', 'ported_number', 'Ported_number', 'portedNumber', 'is_ported'],
        'client_ref' => ['client_ref', 'clientRef', 'request-id', 'request_id', 'requestId', 'reference', 'ref', 'order_id', 'orderId'],
    ];

    /**
     * Fields other providers accept that mean nothing here. Listed so they are
     * silently ignored rather than looking like they did something:
     *   bypass          - skip prefix validation; we never validate prefixes
     *   payment_medium  - source wallet selection; we only have one wallet
     */
    public const IGNORED = ['bypass', 'payment_medium'];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>  canonical fields, merged over the input
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $input) && $input[$alias] !== null && $input[$alias] !== '') {
                    $out[$canonical] = $input[$alias];

                    break;
                }
            }
        }

        return [
            'network' => self::network($out['network'] ?? null),
            'plan_id' => $out['plan_id'] ?? null,
            'phone' => self::phone($out['phone'] ?? null),
            'ported' => filter_var($out['ported'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'client_ref' => self::clientRef($out['client_ref'] ?? null),
        ];
    }

    /**
     * "1" | 1 | "MTN" | "mtn" all become "mtn".
     *
     * An unrecognised value is passed through unchanged so validation can
     * reject it by name, rather than being nulled into "no network given".
     */
    public static function network(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = strtolower(trim((string) $value));

        if (ctype_digit($raw)) {
            return self::NETWORK_IDS[(int) $raw] ?? $raw;
        }

        return match ($raw) {
            'mtn' => 'mtn',
            'airtel' => 'airtel',
            'glo', 'globacom' => 'glo',
            '9mobile', '9 mobile', '9-mobile', 'ninemobile', 'nine mobile', 'etisalat' => '9mobile',
            default => $raw,
        };
    }

    /**
     * Reduce any of the ways a Nigerian number gets written to 11 local digits.
     *
     *   +2348012345678 / 2348012345678 -> 08012345678
     *   8012345678                     -> 08012345678
     *   0801-234-5678                  -> 08012345678
     */
    public static function phone(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        // Ten digits means the leading zero was dropped, commonly by a
        // spreadsheet or a JSON number.
        if (strlen($digits) === 10 && $digits[0] !== '0') {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * The idempotency key: the caller's own order id, stored verbatim so it can
     * be echoed back on every response. Sending the same id again inside the
     * dedupe window returns the original purchase rather than buying twice.
     *
     * Kept as-is rather than hashed into a UUID because the value has to survive
     * the round trip -- callers match our response against their own order.
     * Collisions between callers are not a concern: the lookup is scoped to the
     * user (DataPurchaseService::initiate).
     */
    public static function clientRef(mixed $value): string
    {
        if ($value === null || $value === '') {
            return (string) Str::uuid();
        }

        return trim((string) $value);
    }

    /**
     * The reference exactly as the caller sent it, before any defaulting --
     * null when they sent none.
     *
     * @param  array<string, mixed>  $input
     */
    public static function originalReference(array $input): ?string
    {
        foreach (self::ALIASES['client_ref'] as $alias) {
            if (! empty($input[$alias])) {
                return trim((string) $input[$alias]);
            }
        }

        return null;
    }
}
