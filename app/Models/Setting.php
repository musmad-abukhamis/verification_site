<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: Settings (table "settings"). Uses snake_case created_at/updated_at.
 */
class Setting extends Model
{
    use HasPrismaId;

    protected $table = 'settings';

    protected $guarded = [];
}
