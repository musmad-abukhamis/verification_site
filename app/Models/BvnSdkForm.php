<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: bvnsdkform (table "bvnsdkform").
 */
class BvnSdkForm extends Model
{
    use HasPrismaId;

    protected $table = 'bvnsdkform';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dateOfBirth' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
