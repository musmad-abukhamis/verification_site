<?php

namespace App\Services\Verification;

/**
 * Every verification service the provider engine can route, and what each one
 * needs to know about itself.
 *
 * Adding an entry here is all it takes for the service to appear in
 * Admin > Verification (provider endpoints, the routing matrix) — nothing else
 * enumerates them.
 *
 * `idempotent` is the important field. A NIN or BVN lookup is a read: if a
 * provider times out we can safely ask the next one, because the worst case is
 * being billed twice for a query. BVN retrieval and IPE clearance *submit* work
 * upstream — a timeout there may mean the request landed, so re-sending it to a
 * second provider would duplicate a real submission. Those stop and wait for
 * reconciliation, exactly like ProcessDataPurchase does for data delivery.
 *
 * `failover` is the stronger form of the same idea, and the async services set
 * it. IPE clearance and NIN validation are jobs, not answers: the provider takes
 * hours (IPE) or days (validation) to do the work, and afterwards only *that*
 * provider can be asked how it went. Sending the same NIN to a second vendor
 * because the first said no does not produce a second opinion — it produces a
 * second bill and a second job. So these services stop at the first provider,
 * whatever the global failover setting says.
 *
 * `status_service` links a submit service to the service that polls it. Polling
 * is a separate catalog entry because it is a separate upstream endpoint with
 * its own method and field map, and the admin routes it independently.
 */
class ServiceCatalog
{
    /**
     * service key => definition
     *
     * - label       : shown in the admin
     * - group       : display grouping
     * - inputs      : canonical input fields, in form order
     * - required    : which of those must be present
     * - idempotent  : safe to fail over after an ambiguous/timed-out call
     * - failover    : may the chain move past the first provider at all (default true)
     * - status_service : for async jobs, the service that polls this one's result
     * - price       : matching ServicePrice::SERVICES key (null = priced by the caller)
     */
    public const SERVICES = [
        'nin.verify' => [
            'label' => 'NIN Verification (by NIN)',
            'group' => 'nin',
            'inputs' => ['nin'],
            'required' => ['nin'],
            'idempotent' => true,
            'price' => 'nin.verify',
        ],
        'nin.phone' => [
            'label' => 'NIN Verification (by Phone)',
            'group' => 'nin',
            'inputs' => ['phone'],
            'required' => ['phone'],
            'idempotent' => true,
            'price' => 'nin.phone',
        ],
        'nin.demographic' => [
            'label' => 'NIN Verification (Demographic)',
            'group' => 'nin',
            'inputs' => ['first_name', 'last_name', 'middle_name', 'gender', 'date_of_birth'],
            'required' => ['first_name', 'last_name', 'gender', 'date_of_birth'],
            'idempotent' => true,
            'price' => 'nin.demographic',
        ],
        'nin.ipe' => [
            'label' => 'NIN IPE Clearance (submit)',
            'group' => 'nin',
            // `field_code` is deliberately absent: it is the provider's own
            // routing code (ArewaSmart wants 002 here), not something a caller
            // supplies, so it belongs in the endpoint's Constant fields. Listing
            // it as an input would render it as a mappable request field that
            // nothing ever populates — a field map only renames values that are
            // actually sent, so the row would silently emit nothing.
            'inputs' => ['nin', 'tracking_id', 'description'],
            'required' => ['tracking_id'],
            // A submission, not a lookup — never re-send on an ambiguous reply.
            'idempotent' => false,
            'failover' => false,
            'status_service' => 'nin.ipe.status',
            'price' => 'nin.ipe',
        ],
        'nin.ipe.status' => [
            'label' => 'NIN IPE Clearance (check status)',
            'group' => 'nin',
            'inputs' => ['tracking_id'],
            'required' => ['tracking_id'],
            // Polling is a read, but it is still pinned to the one provider
            // holding the job, so failover would only ask a stranger.
            'idempotent' => true,
            'failover' => false,
            'price' => null,
        ],
        'nin.validation' => [
            'label' => 'NIN Validation (submit)',
            'group' => 'nin',
            // See nin.ipe: `field_code` is a Constant field, not a caller input.
            'inputs' => ['nin', 'description'],
            'required' => ['nin'],
            'idempotent' => false,
            'failover' => false,
            'status_service' => 'nin.validation.status',
            'price' => 'nin.validation',
        ],
        'nin.validation.status' => [
            'label' => 'NIN Validation (check status)',
            'group' => 'nin',
            'inputs' => ['nin'],
            'required' => ['nin'],
            'idempotent' => true,
            'failover' => false,
            'price' => null,
        ],
        'bvn.verify' => [
            'label' => 'BVN Verification',
            'group' => 'bvn',
            'inputs' => ['bvn'],
            'required' => ['bvn'],
            'idempotent' => true,
            'price' => 'bvn.verify',
        ],
        'bvn.retrieval.phone' => [
            'label' => 'BVN Retrieval (by Phone)',
            'group' => 'bvn',
            'inputs' => ['phone', 'field_code'],
            'required' => ['phone'],
            // Creates an upstream ticket — same rule as nin.ipe.
            'idempotent' => false,
            'price' => 'bvn.retrieve.phone',
        ],
        'account.resolve' => [
            'label' => 'Bank Account Resolution',
            'group' => 'other',
            'inputs' => ['account_number', 'bank_code'],
            'required' => ['account_number', 'bank_code'],
            'idempotent' => true,
            'price' => null,
        ],
    ];

    /** Human labels for the `group` field. */
    public const GROUPS = [
        'nin' => 'NIN Services',
        'bvn' => 'BVN Services',
        'other' => 'Other',
    ];

    public static function has(string $service): bool
    {
        return isset(self::SERVICES[$service]);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $service): ?array
    {
        return self::SERVICES[$service] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::SERVICES);
    }

    public static function label(string $service): string
    {
        return self::SERVICES[$service]['label'] ?? $service;
    }

    /**
     * Whether an ambiguous (timeout / transport error) reply for this service
     * may be retried against the next provider in the chain.
     */
    public static function isIdempotent(string $service): bool
    {
        return (bool) (self::SERVICES[$service]['idempotent'] ?? false);
    }

    /**
     * Whether the chain may try a second provider for this service at all.
     *
     * Distinct from idempotency: an idempotent service is safe to retry after an
     * *ambiguous* reply, whereas this governs every reason to move on, including
     * a flat "no". The async job services say no — see the class docblock.
     */
    public static function allowsFailover(string $service): bool
    {
        return (bool) (self::SERVICES[$service]['failover'] ?? true);
    }

    /**
     * The service that polls this one's result, for async jobs.
     */
    public static function statusService(string $service): ?string
    {
        return self::SERVICES[$service]['status_service'] ?? null;
    }

    /**
     * Whether this service submits work that completes later, rather than
     * answering immediately.
     */
    public static function isAsync(string $service): bool
    {
        return self::statusService($service) !== null;
    }

    /** @return array<int, string> */
    public static function inputs(string $service): array
    {
        return self::SERVICES[$service]['inputs'] ?? [];
    }

    /** @return array<int, string> */
    public static function requiredInputs(string $service): array
    {
        return self::SERVICES[$service]['required'] ?? [];
    }

    public static function priceKey(string $service): ?string
    {
        return self::SERVICES[$service]['price'] ?? null;
    }

    /**
     * The catalog shaped for the admin UI's service pickers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forFrontend(): array
    {
        return array_values(array_map(fn (string $key) => [
            'value' => $key,
            'label' => self::SERVICES[$key]['label'],
            'group' => self::GROUPS[self::SERVICES[$key]['group']] ?? 'Other',
            'inputs' => self::SERVICES[$key]['inputs'],
            'required' => self::SERVICES[$key]['required'],
            'idempotent' => self::SERVICES[$key]['idempotent'],
            'failover' => self::allowsFailover($key),
            'async' => self::isAsync($key),
        ], self::keys()));
    }
}
