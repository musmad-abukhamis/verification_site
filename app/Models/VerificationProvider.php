<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use App\Services\Verification\AuthStyle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An upstream NIN/BVN verification provider, defined entirely by configuration.
 *
 * `credentials` is an encrypted JSON blob whose keys depend on `auth_type`
 * (see App\Services\Verification\AuthStyle); `auth_config` holds the non-secret
 * knobs (header names, body field name) so the admin UI can display them.
 */
class VerificationProvider extends Model
{
    use HasPrismaId;

    protected $fillable = [
        'name', 'slug', 'base_url', 'auth_type', 'auth_config', 'credentials',
        'extra_headers', 'timeout_seconds', 'is_active', 'priority', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'auth_config' => 'array',
            'credentials' => 'encrypted:array',
            'extra_headers' => 'array',
            'timeout_seconds' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(VerificationEndpoint::class, 'provider_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(VerificationRoute::class, 'provider_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(VerificationAttempt::class, 'provider_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The endpoint serving a service, or null when this provider cannot. */
    public function endpointFor(string $service): ?VerificationEndpoint
    {
        return $this->endpoints->firstWhere(
            fn (VerificationEndpoint $e) => $e->service === $service && $e->is_active,
        );
    }

    /**
     * Whether the provider can actually be called: active, and its auth style
     * has every credential it needs.
     */
    public function isUsable(): bool
    {
        return $this->is_active
            && $this->base_url !== ''
            && AuthStyle::isConfigured($this->auth_type, (array) $this->credentials);
    }

    /**
     * Which credential fields are populated, without revealing their values —
     * the admin form shows "set / not set" rather than the secret itself.
     *
     * @return array<string, bool>
     */
    public function credentialStatus(): array
    {
        $credentials = (array) $this->credentials;
        $status = [];

        foreach (AuthStyle::credentialFields($this->auth_type) as $field) {
            $status[$field] = ! empty($credentials[$field]);
        }

        return $status;
    }
}
