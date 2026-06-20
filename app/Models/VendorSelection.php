<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: vendorselection (table "vendorselection").
 */
class VendorSelection extends Model
{
    use HasPrismaId;

    protected $table = 'vendorselection';

    public $timestamps = false;

    protected $guarded = [];
}
