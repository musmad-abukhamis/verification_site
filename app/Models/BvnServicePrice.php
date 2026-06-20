<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: bvnserviceprices (single-row config, string id defaulting "API1").
 */
class BvnServicePrice extends Model
{
    protected $table = 'bvnserviceprices';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $attributes = [
        'id' => 'API1',
    ];

    protected $guarded = [];
}
