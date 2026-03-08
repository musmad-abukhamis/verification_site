<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NinIpeClearance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nin',
        'status',
        'result',
        'comment',
        'old_balance',
        'new_balance',
        'reference',
        'cleared_at',
    ];

    protected $casts = [
        'old_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'cleared_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateReference(): string
    {
        return 'IPE_' . strtoupper(uniqid()) . rand(1000, 9999);
    }
}
