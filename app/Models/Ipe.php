<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: ipe (table "ipe", integer auto-increment id).
 */
class Ipe extends Model
{
    protected $table = 'ipe';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oldBal' => 'float',
            'newBal' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Alias: the old NinIpeClearance exposed the tracking value as `nin`.
     */
    public function getNinAttribute(): ?string
    {
        return $this->trkid;
    }

    public static function generateReference(): string
    {
        return 'IPE_'.strtoupper(uniqid()).random_int(1000, 9999);
    }
}
