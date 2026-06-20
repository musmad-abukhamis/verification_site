<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: Account (table "accounts").
 */
class Account extends Model
{
    use HasPrismaId;

    protected $table = 'accounts';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
