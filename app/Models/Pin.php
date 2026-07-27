<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: Pin.
 */
class Pin extends Model
{
    use HasPrismaId;

    protected $table = 'Pin';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected $hidden = ['hashedPin'];

    protected function casts(): array
    {
        return [
            'failedAttempts' => 'integer',
            'isLocked' => 'boolean',
            'lastFailedAttempt' => 'datetime',
            'lockExpiresAt' => 'datetime',
            'lastChangedAt' => 'datetime',
            'expiresAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
