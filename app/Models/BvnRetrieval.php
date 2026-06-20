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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
