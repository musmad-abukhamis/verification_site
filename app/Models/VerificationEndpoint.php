<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider's implementation of one service: where to POST, what to call the
 * fields, and how to tell success from failure in the reply.
 */
class VerificationEndpoint extends Model
{
    protected $fillable = [
        'provider_id', 'service', 'http_method', 'path', 'body_type',
        'field_map', 'static_fields', 'success_rule', 'response_map', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_map' => 'array',
            'static_fields' => 'array',
            'success_rule' => 'array',
            'response_map' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VerificationProvider::class, 'provider_id');
    }

    /** Full request URL for this endpoint. */
    public function url(string $baseUrl): string
    {
        $path = $this->path;

        // An absolute path in the endpoint wins, so one provider can mix hosts.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    public function isGet(): bool
    {
        return strtoupper($this->http_method) === 'GET';
    }
}
