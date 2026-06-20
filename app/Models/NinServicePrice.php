<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: ninServicePrices (single-row config, string id defaulting "API1").
 */
class NinServicePrice extends Model
{
    protected $table = 'ninServicePrices';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $attributes = [
        'id' => 'API1',
    ];

    protected $guarded = [];
}
