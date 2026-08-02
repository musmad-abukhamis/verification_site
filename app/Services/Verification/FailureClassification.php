<?php

namespace App\Services\Verification;

use App\Services\Nin\VerificationResult;

/**
 * What actually happened to a verification, and therefore whether the user may
 * be billed for it.
 *
 * The engine already draws the only line that matters. ProviderCaller returns:
 *
 *   success  — the provider returned the record.
 *   fail     — the provider read the request and answered "no": not found, bad
 *              parameter, mismatch. HTTP was 2xx-4xx and the body was JSON.
 *   timeout  — nobody answered: connection error, unreadable/HTML body, HTTP
 *              5xx or 429.
 *
 * So `fail` is the billable class and `timeout` is not, and this class exists
 * to keep that decision in one auditable place rather than re-derived at four
 * call sites — plus to catch the two `fail`s that are *ours*, not the
 * provider's:
 *
 *   - the dispatcher's "no provider is configured for this service", which
 *     never left the building;
 *   - a provider declining because *our* upstream account is out of credit or
 *     our key was rejected. The customer's BVN may well be perfectly valid;
 *     billing them for our billing problem is indefensible.
 *
 * Both come back as PROVIDER_ERROR and are refunded, not charged.
 *
 * One wrinkle the outcome message alone cannot express: failover. The
 * dispatcher keeps only the LAST hop's outcome, so a chain where provider 1
 * answered "NIN not exists" and provider 2 then declined because our account
 * with *them* was dry used to classify off provider 2's message and waive a fee
 * provider 1 had already earned. The per-hop trace is therefore consulted
 * whenever the final message is not billable: one definitive negative anywhere
 * in the chain is a verification failure, whatever happened after it.
 */
class FailureClassification
{
    /** The provider returned the record. */
    public const SUCCESS = 'SUCCESS';

    /** The provider processed the request and has no such record. Billable. */
    public const RECORD_NOT_FOUND = 'RECORD_NOT_FOUND';

    /** The provider processed the request and rejected it. Billable. */
    public const FAILED_VERIFICATION = 'FAILED_VERIFICATION';

    /** Nobody answered in time. Not billable. */
    public const TIMEOUT = 'TIMEOUT';

    /** Our side broke, or an exception escaped. Not billable. */
    public const TECHNICAL_ERROR = 'TECHNICAL_ERROR';

    /** The provider/our account is the problem, not the record. Not billable. */
    public const PROVIDER_ERROR = 'PROVIDER_ERROR';

    /**
     * The only two classes the user may be charged for: the provider read the
     * request and gave a definitive negative answer about the record.
     */
    public const BILLABLE = [self::RECORD_NOT_FOUND, self::FAILED_VERIFICATION];

    /**
     * Phrases that mean "the record does not exist", as opposed to "it exists
     * but something did not match". Both bill; the split is for the history
     * screen and the audit trail.
     */
    private const NOT_FOUND_PHRASES = [
        'not found', 'no record', 'does not exist', 'doesnt exist', "doesn't exist",
        'no data', 'not exist', 'unknown record', 'record not available', 'no result',
        'invalid bvn', 'invalid nin', 'incorrect bvn', 'incorrect nin',
    ];

    /**
     * Phrases that mean the provider declined for a reason that is about *us* —
     * our upstream wallet, our credentials, our quota — not about the record the
     * customer asked for. Charging for these would bill the customer for our
     * outage, so they are treated as PROVIDER_ERROR and refunded.
     *
     * Note the ordering constraint: these are matched BEFORE the not-found
     * phrases, because a provider will happily say "insufficient balance to
     * complete this lookup, record not found".
     */
    private const PROVIDER_FAULT_PHRASES = [
        'insufficient balance', 'insufficient fund', 'insufficient credit', 'low balance',
        'wallet balance', 'top up', 'topup', 'out of credit', 'no credit',
        'unauthorized', 'unauthorised', 'invalid api', 'invalid token', 'invalid key',
        'authentication failed', 'access denied', 'forbidden', 'subscription',
        'quota', 'rate limit', 'too many requests', 'service unavailable',
        'maintenance', 'try again later', 'no verification provider',
    ];

    /**
     * Classify a dispatcher outcome.
     *
     * @param  bool  $reachedProvider  false when the chain never actually called
     *                                 anyone (nothing routed, capped at zero) —
     *                                 nothing was processed, so nothing bills.
     */
    public static function fromOutcome(VerificationOutcome $outcome, bool $reachedProvider = true): string
    {
        if ($outcome->isSuccess()) {
            return self::SUCCESS;
        }

        if ($outcome->isTimeout()) {
            return self::TIMEOUT;
        }

        // A `fail` that never reached a provider is a configuration problem.
        if (! $reachedProvider || $outcome->providerId === null) {
            return self::PROVIDER_ERROR;
        }

        // The engine's own "nothing is routed" marker.
        if (($outcome->raw['error'] ?? null) === 'no_provider_configured') {
            return self::PROVIDER_ERROR;
        }

        return self::withChain(self::fromMessage($outcome->message), $outcome->attempts);
    }

    /**
     * Classify the NIN module's result shape (AbstractProviderController and the
     * reseller API speak this, not VerificationOutcome).
     *
     * RoutedProvider already sets `errorCode` from the same three engine
     * outcomes, so this stays consistent with fromOutcome() by construction.
     */
    public static function fromResult(VerificationResult $result): string
    {
        if ($result->success) {
            return self::SUCCESS;
        }

        return match ($result->errorCode) {
            'timeout' => self::TIMEOUT,
            // RoutedProvider emits these for "nothing routed" and "our own code
            // threw" respectively — neither is the customer's doing.
            'provider_unavailable', 'provider_error' => self::PROVIDER_ERROR,
            // RoutedProvider carries the chain's trace through in `raw`, so the
            // failover rule applies here exactly as it does to an outcome.
            default => self::withChain(
                self::fromMessage($result->message),
                is_array($result->raw['attempts'] ?? null) ? $result->raw['attempts'] : [],
            ),
        };
    }

    /**
     * Let an earlier hop's verdict stand when the last hop's did not bill.
     *
     * Only a hop the engine recorded as `fail` counts — that is the one outcome
     * meaning "the provider read the request and answered". A hop that timed out
     * answered nothing and cannot make a chain billable.
     *
     * The chain is in preference order, so the first definitive negative wins;
     * a later provider's dry account or rate limit cannot overturn it.
     *
     * @param  array<int, array<string, mixed>>  $attempts  one entry per hop
     */
    public static function withChain(string $classification, array $attempts): string
    {
        if (self::isBillable($classification) || $attempts === []) {
            return $classification;
        }

        foreach ($attempts as $attempt) {
            if (! is_array($attempt) || ($attempt['outcome'] ?? null) !== 'fail') {
                continue;
            }

            $hop = self::fromMessage(is_string($attempt['message'] ?? null) ? $attempt['message'] : null);

            if (self::isBillable($hop)) {
                return $hop;
            }
        }

        return $classification;
    }

    /**
     * Split a provider-confirmed decline into "no such record" vs "rejected",
     * and pull out the ones that are really our own account failing.
     */
    public static function fromMessage(?string $message): string
    {
        $text = strtolower(trim((string) $message));

        if ($text === '') {
            return self::FAILED_VERIFICATION;
        }

        foreach (self::PROVIDER_FAULT_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return self::PROVIDER_ERROR;
            }
        }

        foreach (self::NOT_FOUND_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return self::RECORD_NOT_FOUND;
            }
        }

        return self::FAILED_VERIFICATION;
    }

    public static function isBillable(string $classification): bool
    {
        return in_array($classification, self::BILLABLE, true);
    }

    /**
     * Whether the failure was the provider's answer (as opposed to a transport
     * or configuration problem) — what the user-facing copy keys off.
     */
    public static function isTechnical(string $classification): bool
    {
        return in_array($classification, [self::TIMEOUT, self::TECHNICAL_ERROR, self::PROVIDER_ERROR], true);
    }
}
