<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: Record (table "Record", integer auto-increment id).
 */
class Record extends Model
{
    protected $table = 'Record';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}
