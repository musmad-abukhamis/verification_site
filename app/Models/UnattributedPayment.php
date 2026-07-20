<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * A funding payment that arrived but could not be matched to a user.
 *
 * Written by the PayVessel / Billstack webhooks in place of the old
 * log-and-forget path, and cleared from the admin Unattributed Payments screen.
 */
class UnattributedPayment extends Model
{
    use HasPrismaId;

    protected $table = 'unattributed_payments';

    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function resolvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Record an unattributable payment.
     *
     * Keyed on the provider reference, so a retried delivery updates the
     * existing row rather than creating a second one. Deliberately never
     * throws: this runs inside a webhook whose caller has already parted with
     * the money, and a failure to record must not turn into a 500 that makes
     * the provider retry forever.
     *
     * An admin decision is never undone by a retry. A resolved payment cannot
     * reach here (the ledger check in the webhook returns first), but an
     * ignored one can -- it has no ledger row -- so status is only ever set on
     * insert.
     */
    public static function record(string $provider, array $attributes): ?self
    {
        try {
            $payment = static::firstOrNew(['reference' => $attributes['reference']]);

            $payment->fill($attributes + ['provider' => $provider]);

            if (! $payment->exists) {
                $payment->status = self::STATUS_PENDING;
            }

            $payment->save();

            return $payment;
        } catch (\Throwable $e) {
            Log::error('Could not record unattributed payment', [
                'provider' => $provider,
                'reference' => $attributes['reference'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
