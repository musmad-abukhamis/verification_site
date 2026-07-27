<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: TwoFactorConfirmation.
 */
class TwoFactorConfirmation extends Model
{
    use HasPrismaId;

    protected $table = 'TwoFactorConfirmation';

    public $timestamps = false;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
