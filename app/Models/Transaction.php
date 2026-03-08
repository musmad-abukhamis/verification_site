<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'status',
        'amount',
        'fee',
        'total_amount',
        'details',
        'provider',
        'provider_reference',
        'response_message',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'details' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSuccess(?string $providerReference = null, ?string $message = null): void
    {
        $this->update([
            'status' => 'success',
            'provider_reference' => $providerReference,
            'response_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $message = null): void
    {
        $this->update([
            'status' => 'failed',
            'response_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public static function generateReference(): string
    {
        return 'TXN_' . strtoupper(uniqid()) . rand(1000, 9999);
    }
}
