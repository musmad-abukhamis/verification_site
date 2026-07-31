<?php

namespace App\Services\Verification;

use App\Models\FailedVerificationCharge;
use App\Models\User;
use App\Models\VerificationSetting;
use App\Services\Nin\VerificationResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Charges the admin-configured fee for a NIN/BVN verification the provider
 * confirmed as failed.
 *
 * Every verification flow in this app already debits the service price up
 * front and refunds it in full on any non-success. That stays exactly as it is:
 * this service runs *after* the refund and takes a separate, smaller fee, so
 * there is never a double charge and the existing success pricing is untouched.
 *
 * The three rules that matter:
 *
 *  1. Only FailureClassification::BILLABLE — the provider read the request and
 *     answered "no such record" / "rejected". A timeout, a 5xx, an exception or
 *     our own upstream account failing is refunded and never billed.
 *  2. The debit is atomic. The user row is locked for the whole check-and-debit
 *     so two verifications landing at once cannot both pass a balance check.
 *  3. It is idempotent twice over: `failed_verification_charges
 *     .verification_reference` is unique, and the `wallethistory` row's id is
 *     derived from that same reference, so a replayed request cannot produce a
 *     second debit even if the first row were somehow missed.
 */
class FailedVerificationChargeService
{
    /** identity type => the VerificationSetting keys that drive it. */
    public const SETTINGS = [
        'bvn' => ['enabled' => 'failed_charge_bvn_enabled', 'amount' => 'failed_charge_bvn_amount'],
        'nin' => ['enabled' => 'failed_charge_nin_enabled', 'amount' => 'failed_charge_nin_amount'],
    ];

    /** wallethistory.fundingtype — the category the ledger and reports group by. */
    public const CATEGORIES = [
        'bvn' => 'bvn_verification_failed',
        'nin' => 'nin_verification_failed',
    ];

    /**
     * How long the same user re-submitting the same identity counts as the same
     * attempt rather than a new one. Covers the double-clicked button and the
     * frontend that retries, both of which mint a fresh reference and so would
     * otherwise slip past the unique key. Admin-tunable; 0 disables it.
     */
    private const DEDUPE_SECONDS_KEY = 'failed_charge_dedupe_seconds';

    private const DEDUPE_SECONDS_DEFAULT = 60;

    /**
     * Which toggle governs a service. Only NIN and BVN identity lookups are in
     * scope — account.resolve and friends return null and are never charged.
     */
    public static function identityTypeFor(string $service): ?string
    {
        return match (true) {
            str_starts_with($service, 'bvn.') => 'bvn',
            str_starts_with($service, 'nin.') => 'nin',
            default => null,
        };
    }

    public function isEnabled(?string $identityType): bool
    {
        if ($identityType === null || ! isset(self::SETTINGS[$identityType])) {
            return false;
        }

        return VerificationSetting::bool(self::SETTINGS[$identityType]['enabled'], false);
    }

    /**
     * The configured fee. Never hardcoded — an unset key means zero, which is
     * treated as "no charge".
     */
    public function amountFor(?string $identityType): float
    {
        if ($identityType === null || ! isset(self::SETTINGS[$identityType])) {
            return 0.0;
        }

        return max(0.0, round(VerificationSetting::float(self::SETTINGS[$identityType]['amount'], 0.0), 2));
    }

    /**
     * Is a fee actually live for this service right now? Callers use this to
     * decide whether to mention charging in their copy at all.
     */
    public function isActiveFor(string $service): bool
    {
        $type = self::identityTypeFor($service);

        return $this->isEnabled($type) && $this->amountFor($type) > 0;
    }

    /**
     * Charge (or explain why not) for a dispatcher outcome.
     *
     * @param  array<string, mixed>  $context  identity_value, record_id, message
     */
    public function chargeForOutcome(
        User $user,
        string $service,
        string $reference,
        VerificationOutcome $outcome,
        array $context = [],
    ): FailedVerificationChargeResult {
        // No hop was traced ⇒ the chain never reached anyone, so the request was
        // never processed and cannot be a verification failure.
        $reachedProvider = $outcome->attempts !== [] || $outcome->providerId !== null;

        return $this->charge($user, $service, $reference, FailureClassification::fromOutcome($outcome, $reachedProvider), [
            'provider_name' => $outcome->providerName,
            'message' => $outcome->message,
        ] + $context);
    }

    /**
     * Charge (or explain why not) for the NIN module's result shape.
     *
     * @param  array<string, mixed>  $context
     */
    public function chargeForResult(
        User $user,
        string $service,
        string $reference,
        VerificationResult $result,
        array $context = [],
    ): FailedVerificationChargeResult {
        return $this->charge($user, $service, $reference, FailureClassification::fromResult($result), [
            'provider_name' => $result->data['provider'] ?? null,
            'message' => $result->message,
        ] + $context);
    }

    /**
     * The full decision, in the order the spec lays out: enabled? priced?
     * billable? already charged? affordable? — then, and only then, money moves.
     *
     * @param  array<string, mixed>  $context  identity_value, provider_name, message, record_id
     */
    public function charge(
        User $user,
        string $service,
        string $reference,
        string $classification,
        array $context = [],
    ): FailedVerificationChargeResult {
        $type = self::identityTypeFor($service);

        // Nothing to say, and nothing worth recording, when the feature is off.
        if (! $this->isEnabled($type)) {
            return FailedVerificationChargeResult::notCharged(
                FailedVerificationCharge::REASON_DISABLED,
                classification: $classification,
            );
        }

        $amount = $this->amountFor($type);

        if ($amount <= 0) {
            return FailedVerificationChargeResult::notCharged(
                FailedVerificationCharge::REASON_ZERO_AMOUNT,
                classification: $classification,
            );
        }

        // A success never reaches here, but guard anyway: the successful-
        // verification price is charged by the caller and is not our business.
        if ($classification === FailureClassification::SUCCESS) {
            return FailedVerificationChargeResult::notCharged(
                FailedVerificationCharge::REASON_NOT_BILLABLE,
                classification: $classification,
            );
        }

        // The columns every branch shares. Per-branch values are unioned on the
        // LEFT of this array (`[...] + $base`) — PHP's array union keeps the
        // left operand's value for a duplicate key, so overrides must lead.
        $base = [
            'user_id' => $user->getKey(),
            'identity_type' => $type,
            'service' => $service,
            'verification_reference' => $reference,
            'classification' => $classification,
            'amount' => 0,
            'charged' => false,
            'identity_hash' => $this->hashIdentity($context['identity_value'] ?? null),
            'provider_name' => $context['provider_name'] ?? null,
            'message' => $context['message'] ?? null,
            'record_id' => $context['record_id'] ?? null,
        ];

        // Technical failure: log that we deliberately did NOT bill, so the
        // history can prove it, then stop.
        if (! FailureClassification::isBillable($classification)) {
            $record = $this->record([
                'reason' => FailedVerificationCharge::REASON_NOT_BILLABLE,
                'balance_before' => (float) $user->balance,
                'balance_after' => (float) $user->balance,
            ] + $base);

            return FailedVerificationChargeResult::notCharged(
                FailedVerificationCharge::REASON_NOT_BILLABLE,
                $record,
                $classification,
            );
        }

        return $this->debit($user, $type, $reference, $classification, $amount, $base);
    }

    /**
     * The atomic part. Everything from "is the balance enough" to "the ledger
     * row exists" happens under one row lock on the user, so two verifications
     * finishing at the same instant serialize instead of both spending the same
     * naira.
     *
     * @param  array<string, mixed>  $base
     */
    private function debit(
        User $user,
        string $type,
        string $reference,
        string $classification,
        float $amount,
        array $base,
    ): FailedVerificationChargeResult {
        $walletReference = self::walletReference($type, $reference);

        return DB::transaction(function () use ($user, $type, $reference, $classification, $amount, $base, $walletReference) {
            // Lock first: it is what makes the checks below meaningful.
            $locked = User::whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return FailedVerificationChargeResult::notCharged(
                    FailedVerificationCharge::REASON_NOT_BILLABLE,
                    classification: $classification,
                );
            }

            // (a) This exact attempt already settled — replay, retry, refresh.
            $existing = FailedVerificationCharge::where('verification_reference', $reference)->first();

            if ($existing) {
                return $this->resultFor($existing);
            }

            // (b) The same identity from the same user moments ago: a double
            // click or a frontend retry, which mints a new reference each time
            // and so would sail past (a).
            if ($duplicate = $this->recentChargeFor($locked, $base['identity_type'], $base['identity_hash'])) {
                $record = $this->record([
                    'charged' => false,
                    'reason' => FailedVerificationCharge::REASON_DUPLICATE,
                    'wallet_history_id' => $duplicate->wallet_history_id,
                    'balance_before' => (float) $locked->balance,
                    'balance_after' => (float) $locked->balance,
                ] + $base);

                return FailedVerificationChargeResult::notCharged(
                    FailedVerificationCharge::REASON_DUPLICATE,
                    $record,
                    $classification,
                    $duplicate->amount,
                );
            }

            $balanceBefore = (float) $locked->balance;

            // (c) Never take the balance negative — this app has no overdraft.
            if ($balanceBefore < $amount) {
                $record = $this->record([
                    'charged' => false,
                    'amount' => $amount,
                    'reason' => FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore,
                ] + $base);

                $this->logDeduction($record, 'skipped: insufficient balance');

                return FailedVerificationChargeResult::notCharged(
                    FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE,
                    $record,
                    $classification,
                    $amount,
                );
            }

            // The ledger id is derived from the verification reference, so the
            // primary key itself rejects a second debit for the same attempt.
            try {
                $debited = $user->debit($amount, false, [
                    'id' => $walletReference,
                    'fundingtype' => self::CATEGORIES[$type],
                    'status' => 'success',
                ]);
            } catch (QueryException $e) {
                // The ledger row already exists: this attempt was charged by an
                // earlier pass whose audit row we somehow did not see. The
                // money is right; do not take it twice.
                $record = $this->record([
                    'charged' => false,
                    'amount' => $amount,
                    'reason' => FailedVerificationCharge::REASON_DUPLICATE,
                    'wallet_history_id' => $walletReference,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore,
                ] + $base);

                return FailedVerificationChargeResult::notCharged(
                    FailedVerificationCharge::REASON_DUPLICATE,
                    $record,
                    $classification,
                    $amount,
                );
            }

            if (! $debited) {
                // Lost a race we thought we had locked out. Record it rather
                // than pretending money moved.
                $record = $this->record([
                    'charged' => false,
                    'amount' => $amount,
                    'reason' => FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore,
                ] + $base);

                return FailedVerificationChargeResult::notCharged(
                    FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE,
                    $record,
                    $classification,
                    $amount,
                );
            }

            $record = $this->record([
                'charged' => true,
                'amount' => $amount,
                'reason' => FailedVerificationCharge::REASON_CHARGED,
                'wallet_history_id' => $walletReference,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $user->balance,
            ] + $base);

            $this->logDeduction($record, 'charged');

            return new FailedVerificationChargeResult(
                true,
                $amount,
                FailedVerificationCharge::REASON_CHARGED,
                $walletReference,
                $record,
                $classification,
            );
        });
    }

    /**
     * A charge for the same user + identity inside the dedupe window.
     */
    private function recentChargeFor(User $user, string $type, ?string $identityHash): ?FailedVerificationCharge
    {
        $window = VerificationSetting::int(self::DEDUPE_SECONDS_KEY, self::DEDUPE_SECONDS_DEFAULT);

        if ($identityHash === null || $window <= 0) {
            return null;
        }

        return FailedVerificationCharge::where('user_id', $user->getKey())
            ->where('identity_type', $type)
            ->where('identity_hash', $identityHash)
            ->where('charged', true)
            ->where('created_at', '>=', now()->subSeconds($window))
            ->latest('id')
            ->first();
    }

    /**
     * Write the audit row, tolerating the unique-key race: if another request
     * inserted the same reference between our read and our write, that row is
     * the truth and we adopt it rather than blowing up a verification response.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): FailedVerificationCharge
    {
        try {
            return FailedVerificationCharge::create($attributes);
        } catch (QueryException $e) {
            $existing = FailedVerificationCharge::where(
                'verification_reference',
                $attributes['verification_reference'],
            )->first();

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    private function resultFor(FailedVerificationCharge $record): FailedVerificationChargeResult
    {
        if (! $record->charged) {
            return FailedVerificationChargeResult::notCharged(
                $record->reason,
                $record,
                $record->classification,
                (float) $record->amount,
            );
        }

        return new FailedVerificationChargeResult(
            true,
            (float) $record->amount,
            $record->reason,
            $record->wallet_history_id,
            $record,
            $record->classification,
        );
    }

    /**
     * Deterministic ledger id for an attempt — same reference, same id, so the
     * `wallethistory` primary key is the last line of defence against a double
     * debit. Hashed rather than concatenated because references vary in length
     * and format across the four flows.
     */
    public static function walletReference(string $identityType, string $verificationReference): string
    {
        return 'FVC-'.strtoupper($identityType).'-'
            .strtoupper(substr(hash('sha256', $identityType.'|'.$verificationReference), 0, 20));
    }

    /**
     * Identities are hashed, never stored: the dedupe window needs to know two
     * submissions were the same BVN, not what the BVN was.
     */
    private function hashIdentity(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : null;

        return $value === null || $value === '' ? null : hash('sha256', $value);
    }

    /**
     * The deduction audit line. Carries the reference, the amount and the
     * balances either side — deliberately not the BVN/NIN.
     */
    private function logDeduction(FailedVerificationCharge $record, string $outcome): void
    {
        Log::info('[failed-verification-charge] '.$outcome, [
            'user_id' => $record->user_id,
            'identity_type' => $record->identity_type,
            'service' => $record->service,
            'verification_reference' => $record->verification_reference,
            'classification' => $record->classification,
            'amount' => (float) $record->amount,
            'balance_before' => $record->balance_before,
            'balance_after' => $record->balance_after,
            'transaction_reference' => $record->wallet_history_id,
            'reason' => $record->reason,
        ]);
    }
}
