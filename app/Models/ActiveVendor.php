<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'network',
        'type',
        'vendor_number',
    ];

    public function getVendorApiAttribute()
    {
        return 'vendor' . $this->vendor_number;
    }

    public function getVendorUrlAttribute()
    {
        return $this->vendorApi . 'url';
    }

    public function getVendorKeyAttribute()
    {
        return $this->vendorApi . 'key';
    }
}