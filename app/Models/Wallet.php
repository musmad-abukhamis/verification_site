<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'bonus_balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'bonus_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->balance + (float) $this->bonus_balance;
    }

    public function debit(float $amount, bool $useBonus = false): bool
    {
        if ($useBonus && $this->bonus_balance >= $amount) {
            $this->decrement('bonus_balance', $amount);
            return true;
        }

        if ($this->balance >= $amount) {
            $this->decrement('balance', $amount);
            return true;
        }

        return false;
    }

    public function credit(float $amount, bool $isBonus = false): void
    {
        $field = $isBonus ? 'bonus_balance' : 'balance';
        $this->increment($field, $amount);
    }
}
