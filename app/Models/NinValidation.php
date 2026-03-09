<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NinValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nin',
        'id_type',
        'id_value',
        'status',
        'result',
        'comment',
        'old_balance',
        'new_balance',
        'reference',
        'provider',
        'verification_reference',
        'verification_fee',
        'is_verified',
        'validated_at',
    ];

    protected $casts = [
        'old_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'verification_fee' => 'decimal:2',
        'is_verified' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for verified records only
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true)->where('status', 'completed');
    }

    /**
     * Scope for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this verification is valid for slip download
     */
    public function isVerificationValid(): bool
    {
        return $this->is_verified && $this->status === 'completed';
    }

    /**
     * Get the parsed result data
     */
    public function getParsedResult(): ?array
    {
        if (!$this->result) {
            return null;
        }

        try {
            return json_decode($this->result, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function generateReference(): string
    {
        return 'NIN_' . strtoupper(uniqid()) . rand(1000, 9999);
    }
}
