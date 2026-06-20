<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: accountkyc (table "accountkyc").
 */
class AccountKyc extends Model
{
    use HasPrismaId;

    protected $table = 'accountkyc';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
