<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hop of a verification call — including the hops that failed and were
 * failed over. `provider_name` is denormalized so the audit trail survives the
 * provider being deleted.
 */
class VerificationAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service', 'provider_id', 'provider_name', 'user_id', 'reference',
        'request_payload', 'response', 'outcome', 'http_status', 'duration_ms', 'message',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response' => 'array',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VerificationProvider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
