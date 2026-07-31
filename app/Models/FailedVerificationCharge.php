<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider-confirmed (or technically failed) NIN/BVN verification, and what
 * it cost the user.
 *
 * Rows exist for *not*-charged failures too — that is the point: the user's
 * history has to be able to say "this failed and you were not billed, because
 * the provider never answered".
 *
 * `verification_reference` is unique, which is what makes the debit idempotent.
 */
class FailedVerificationCharge extends Model
{
    /** No charge was taken because... */
    public const REASON_CHARGED = 'CHARGED';

    public const REASON_DISABLED = 'CHARGING_DISABLED';

    public const REASON_ZERO_AMOUNT = 'ZERO_AMOUNT';

    public const REASON_NOT_BILLABLE = 'NOT_A_VERIFICATION_FAILURE';

    public const REASON_INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';

    public const REASON_DUPLICATE = 'DUPLICATE_ATTEMPT';

    protected $fillable = [
        'user_id', 'identity_type', 'service', 'verification_reference', 'classification',
        'charged', 'amount', 'reason', 'wallet_history_id', 'balance_before', 'balance_after',
        'identity_hash', 'provider_name', 'message', 'record_id',
    ];

    protected function casts(): array
    {
        return [
            'charged' => 'boolean',
            'amount' => 'float',
            'balance_before' => 'float',
            'balance_after' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The shape the verification-history screens render.
     *
     * @return array<string, mixed>
     */
    public function toHistoryPayload(): array
    {
        return [
            'charged' => $this->charged,
            'charge_amount' => $this->charged ? (float) $this->amount : 0.0,
            'charge_reason' => $this->reason,
            'charge_reference' => $this->wallet_history_id,
            'classification' => $this->classification,
        ];
    }

    /**
     * History payload for a failure that predates this feature (or was never
     * evaluated), so the frontend can bind the same fields unconditionally.
     *
     * @return array<string, mixed>
     */
    public static function emptyHistoryPayload(): array
    {
        return [
            'charged' => false,
            'charge_amount' => 0.0,
            'charge_reason' => null,
            'charge_reference' => null,
            'classification' => null,
        ];
    }
}
