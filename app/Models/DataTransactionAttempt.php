<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransactionAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'data_transaction_id', 'vendor_id', 'request_payload', 'response', 'outcome',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DataTransaction::class, 'data_transaction_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
