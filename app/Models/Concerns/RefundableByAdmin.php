<?php

namespace App\Models\Concerns;

/**
 * A staff-fulfilled request whose fee an admin can hand back once.
 *
 * Expects the Prisma-ported `oldBal` / `newBal` string columns plus the
 * `refundedAt` / `refundAmount` columns added for refunds. A model with a more
 * direct record of the fee overrides {@see chargedAmount()}.
 */
trait RefundableByAdmin
{
    public function initializeRefundableByAdmin(): void
    {
        $this->mergeCasts(['refundedAt' => 'datetime']);
    }

    /**
     * What the user actually paid for this request.
     *
     * The balance delta stamped on the row at submission: the controllers debit
     * once before creating the record, and today's price may differ from what an
     * older row was charged.
     */
    public function chargedAmount(): float
    {
        $delta = (float) $this->oldBal - (float) $this->newBal;

        return $delta > 0 ? round($delta, 2) : 0.0;
    }

    /**
     * Whether the admin refund button should be live: not yet refunded, and
     * something was actually charged (free or pre-fee rows carry no delta).
     */
    public function isRefundable(): bool
    {
        return $this->refundedAt === null && $this->chargedAmount() > 0;
    }
}
