<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: networks (table "networks").
 */
class Network extends Model
{
    use HasPrismaId;

    protected $table = 'networks';

    public $timestamps = false;

    protected $guarded = [];
}
