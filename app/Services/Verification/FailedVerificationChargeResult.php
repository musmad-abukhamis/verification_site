<?php

namespace App\Services\Verification;

use App\Models\FailedVerificationCharge;

/**
 * What FailedVerificationChargeService did — and, when it did nothing, why.
 *
 * Callers use `charged` to decide whether to tell the user money moved, and
 * `notice()` for the sentence to append to the failure message.
 */
class FailedVerificationChargeResult
{
    public function __construct(
        public readonly bool $charged,
        public readonly float $amount = 0.0,
        public readonly string $reason = FailedVerificationCharge::REASON_DISABLED,
        public readonly ?string $transactionReference = null,
        public readonly ?FailedVerificationCharge $record = null,
        public readonly ?string $classification = null,
    ) {}

    public static function notCharged(
        string $reason,
        ?FailedVerificationCharge $record = null,
        ?string $classification = null,
        float $amount = 0.0,
    ): self {
        return new self(false, $amount, $reason, null, $record, $classification);
    }

    public function wasInsufficientBalance(): bool
    {
        return $this->reason === FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE;
    }

    /**
     * The sentence to show the user underneath "Verification failed".
     *
     * Returns null when there is nothing worth saying — charging is switched
     * off, or this was not a chargeable event in the first place — so the
     * existing copy is left alone rather than gaining a confusing "you were not
     * charged" on a feature the site does not use.
     */
    public function notice(): ?string
    {
        if ($this->charged) {
            return '₦'.number_format($this->amount, 2)
                .' has been deducted from your wallet for this verification attempt.';
        }

        return match ($this->reason) {
            FailedVerificationCharge::REASON_INSUFFICIENT_BALANCE => 'Insufficient wallet balance — no failed-verification fee was charged.',
            FailedVerificationCharge::REASON_NOT_BILLABLE => 'No amount was deducted from your wallet.',
            FailedVerificationCharge::REASON_DUPLICATE => 'You were charged once for this attempt; this repeat was not billed.',
            default => null,
        };
    }

    /**
     * Append notice() to a provider message, when there is one.
     */
    public function decorate(?string $message): string
    {
        $message = trim((string) $message) ?: 'Verification failed.';
        $notice = $this->notice();

        return $notice === null ? $message : rtrim($message, ' ').' '.$notice;
    }

    /**
     * The conceptual response the spec asks for, for JSON callers.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'charged' => $this->charged,
            'amount' => $this->amount,
            'reason' => $this->charged ? null : $this->reason,
            'transactionReference' => $this->transactionReference,
        ], fn ($value) => $value !== null);
    }
}
