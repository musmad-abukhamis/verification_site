<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: PinVerificationLog.
 */
class PinVerificationLog extends Model
{
    use HasPrismaId;

    protected $table = 'PinVerificationLog';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }
}
