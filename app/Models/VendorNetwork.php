<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorNetwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'network',
        'vendor1network',
        'vendor2network',
        'vendor3network',
        'vendor4network',
        'vendor5network',
    ];
}