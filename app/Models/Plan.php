<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'network',
        'name',
        'price',
        'agentPrice',
        'apiPrice',
        'type',
        'validity',
        'status',
        'planStatus',
        'apiKey',
        'vendorPlan1',
        'vendorPlan2',
        'vendorPlan3',
        'vendorPlan4',
        'vendorPlan5',
    ];

    protected $casts = [
        'price' => 'integer',
        'agentPrice' => 'integer',
        'apiPrice' => 'integer',
        'apiKey' => 'integer',
    ];
}