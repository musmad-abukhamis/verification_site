<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: VerificationToken.
 */
class VerificationToken extends Model
{
    use HasPrismaId;

    protected $table = 'VerificationToken';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires' => 'datetime',
        ];
    }
}
