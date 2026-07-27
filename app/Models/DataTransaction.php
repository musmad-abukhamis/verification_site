<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A data purchase. The primary key doubles as the human-facing reference
 * (Data_{ms}_{rand6}) and is assigned by DataPurchaseService, not auto-generated.
 */
class DataTransaction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'plan_id', 'status', 'network', 'type', 'plan_name', 'price',
        'phone', 'ported', 'vendor_id', 'vendor_reference', 'attempts',
        'oldbal', 'newbal', 'raw_response', 'client_ref',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'oldbal' => 'float',
            'newbal' => 'float',
            'ported' => 'boolean',
            'attempts' => 'integer',
            'raw_response' => 'array',
        ];
    }

    public const TERMINAL = ['success', 'fail', 'refunded', 'refunded_unconfirmed'];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function attemptLogs(): HasMany
    {
        return $this->hasMany(DataTransactionAttempt::class, 'data_transaction_id');
    }
}
