<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: bvnRetrieval (table "bvnRetrieval").
 */
class BvnRetrieval extends Model
{
    use HasPrismaId;

    protected $table = 'bvnRetrieval';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected $casts = [
        'refundedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * What the user actually paid for this request.
     *
     * Unlike the async NIN jobs there is no `price` column here, so the balance
     * delta stamped on the row at submission stands in. It is reliable for the
     * purpose: the controller debits once before creating the record, and the
     * fee is role-aware and admin-editable, so today's `bvn.retrieve.id` price
     * may be a different number from what a week-old row was charged.
     *
     * The balance columns are strings in this Prisma-ported table.
     */
    public function chargedAmount(): float
    {
        $delta = (float) $this->oldBal - (float) $this->newBal;

        return $delta > 0 ? round($delta, 2) : 0.0;
    }

    /**
     * Whether the admin refund button should be live.
     *
     * Rows created before the fee existed — and any written by a path that did
     * not debit — carry no delta, and there is nothing to return on those.
     */
    public function isRefundable(): bool
    {
        return $this->refundedAt === null && $this->chargedAmount() > 0;
    }
}
