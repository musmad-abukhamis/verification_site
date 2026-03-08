<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorApi extends Model
{
    use HasFactory;

    protected $table = 'vendorapi';

    protected $fillable = [
        'vendor1url',
        'vendor1key',
        'vendor2url',
        'vendor2key',
        'vendor3url',
        'vendor3key',
        'vendor4url',
        'vendor4key',
        'vendor5url',
        'vendor5key',
    ];
}