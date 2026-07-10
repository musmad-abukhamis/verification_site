<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dedicated ledger for the data module. One row per balance mutation, written
 * inside the same DB transaction as the users.balance update.
 */
class WalletEntry extends Model
{
    use HasPrismaId;

    protected $fillable = [
        'user_id', 'direction', 'amount', 'balance_after', 'reason', 'data_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
