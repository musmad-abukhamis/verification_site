<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorSelection extends Model
{
    protected $fillable = [
        'id',
        'SME',
        'SME2',
        'CORPORATE_GIFTING',
        'CORPORATE_GIFTING2',
        'DATASHARE'
    ];

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $table = 'vendorselections';
}