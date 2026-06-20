<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: IdCard (table "IdCard").
 */
class IdCard extends Model
{
    use HasPrismaId;

    protected $table = 'IdCard';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected $hidden = ['passportImage'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
