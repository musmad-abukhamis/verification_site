<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'vendor_number',
        'vendor_plan_code',
        'is_active',
    ];

    protected $casts = [
        'vendor_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function dataPlan()
    {
        return $this->belongsTo(DataPlan::class, 'plan_id');
    }
}