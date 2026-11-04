<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use App\Models\Concerns\RefundableByAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: BvnModification (table "BvnModification").
 */
class BvnModification extends Model
{
    use HasPrismaId;
    use RefundableByAdmin {
        chargedAmount as private balanceDelta;
    }

    protected $table = 'BvnModification';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected $hidden = ['ninSlipImage'];

    protected function casts(): array
    {
        return [
            'oldDob' => 'datetime',
            'newDob' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * What the user paid: `amountCharged` is stamped by both the web form and the
     * reseller API; the balance delta covers legacy rows that lack it.
     */
    public function chargedAmount(): float
    {
        $stamped = (float) $this->amountCharged;

        return $stamped > 0 ? round($stamped, 2) : $this->balanceDelta();
    }
}
