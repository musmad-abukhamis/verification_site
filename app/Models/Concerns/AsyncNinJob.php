<?php

namespace App\Models\Concerns;

use App\Models\VerificationProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Behaviour shared by the two NIN services that submit work and collect the
 * result later: Validation (3 days–1 week) and Ipe (30 minutes–3 hours).
 *
 * Both rows are Prisma-ported and identical in every respect that matters here —
 * same status vocabulary, same balance columns, same provider/refund columns
 * added alongside them.
 */
trait AsyncNinJob
{
    /** Statuses that mean the job is finished, one way or the other. */
    public const TERMINAL_STATUSES = ['completed', 'failed'];

    /** The statuses an admin may set by hand. */
    public const EDITABLE_STATUSES = ['processing', 'completed', 'failed'];

    /**
     * The provider holding this job. Null on rows submitted before the column
     * existed, and on rows whose provider has since been deleted.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(VerificationProvider::class, 'providerId');
    }

    /** Jobs still waiting on the provider. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function isTerminal(): bool
    {
        return in_array(strtolower((string) $this->status), self::TERMINAL_STATUSES, true);
    }

    /**
     * What the user actually paid for this job.
     *
     * `price` has been recorded since the async rework; older rows predate it,
     * so the balance delta stands in. That delta is the same number for every
     * row written by the old controllers, which always debited once before
     * creating the record.
     */
    public function chargedAmount(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        $delta = (float) $this->oldBal - (float) $this->newBal;

        return $delta > 0 ? round($delta, 2) : 0.0;
    }

    /**
     * Whether the admin refund button should be live.
     *
     * Refusing a second refund is the whole point of `refundedAt`: these jobs
     * are reviewed by hand, days apart, often by different admins, and nothing
     * else in the record distinguishes "refunded" from "not yet".
     */
    public function isRefundable(): bool
    {
        return $this->refundedAt === null && $this->chargedAmount() > 0;
    }
}
