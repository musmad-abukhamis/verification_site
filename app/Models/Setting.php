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

    /** Shown until the settings row exists; matches the column default. */
    public const DEFAULT_AFFILIATE_SITE_FEE = 100000;

    protected $table = 'settings';

    protected $guarded = [];
}
