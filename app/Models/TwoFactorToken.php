<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: TwoFactorToken.
 */
class TwoFactorToken extends Model
{
    use HasPrismaId;

    protected $table = 'TwoFactorToken';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires' => 'datetime',
        ];
    }
}
