<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: PasswordResetToken (NextAuth-style, distinct from Laravel's
 * snake_case password_reset_tokens table used by Breeze).
 */
class PasswordResetToken extends Model
{
    use HasPrismaId;

    protected $table = 'PasswordResetToken';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires' => 'datetime',
        ];
    }
}
