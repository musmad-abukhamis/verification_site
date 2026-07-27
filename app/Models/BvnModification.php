<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: BvnModification (table "BvnModification").
 */
class BvnModification extends Model
{
    use HasPrismaId;

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
}
