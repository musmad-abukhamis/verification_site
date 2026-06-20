<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: NINDetails (table "NINDetails").
 */
class NinDetail extends Model
{
    use HasPrismaId;

    protected $table = 'NINDetails';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oldBal' => 'float',
            'newBal' => 'float',
            'price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
