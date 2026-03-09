<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    // Transaction type constants
    public const TYPE_AIRTIME = 'airtime';
    public const TYPE_DATA = 'data';
    public const TYPE_NIN_VERIFICATION = 'nin_verification';
    public const TYPE_BVN_VERIFICATION = 'bvn_verification';
    public const TYPE_WALLET_FUNDING = 'wallet_funding';
    public const TYPE_REFUND = 'refund';
    public const TYPE_NIN_SLIP_DOWNLOAD = 'nin_slip_download';

    public const TYPES = [
        self::TYPE_AIRTIME,
        self::TYPE_DATA,
        self::TYPE_NIN_VERIFICATION,
        self::TYPE_BVN_VERIFICATION,
        self::TYPE_WALLET_FUNDING,
        self::TYPE_REFUND,
        self::TYPE_NIN_SLIP_DOWNLOAD,
    ];

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

    /**
     * Create a slip download transaction
     */
    public static function createSlipDownload(int $userId, float $amount, array $details): self
    {
        return self::create([
            'user_id' => $userId,
            'reference' => self::generateReference(),
            'type' => self::TYPE_NIN_SLIP_DOWNLOAD,
            'status' => 'success',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => $details,
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a verification transaction
     */
    public static function createVerification(int $userId, float $amount, string $type, array $details): self
    {
        return self::create([
            'user_id' => $userId,
            'reference' => self::generateReference(),
            'type' => $type,
            'status' => 'success',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => $details,
            'completed_at' => now(),
        ]);
    }
}
