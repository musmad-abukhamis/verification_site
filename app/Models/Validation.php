<?php

namespace App\Models;

use App\Models\Concerns\AsyncNinJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: validation (table "validation", integer auto-increment id).
 *
 * Stores NIN verification *and* NIN validation records — two different
 * services sharing one table. `service` says which wrote the row, and every
 * read has to scope by it: a lookup listed as a validation (or the reverse) is
 * the wrong price, the wrong turnaround and the wrong story about the user's
 * money. Use the `verifications()` / `validations()` scopes rather than
 * spelling the keys out at each call site.
 *
 * `result` holds the raw provider JSON payload (used to render downloadable
 * slips), `comment` is a human label.
 */
class Validation extends Model
{
    use AsyncNinJob;

    /** ServiceCatalog keys written by the instant-lookup (verification) paths. */
    public const VERIFY_SERVICES = ['nin.verify', 'nin.phone', 'nin.demographic'];

    /** ServiceCatalog key written by the async NIN Validation paths. */
    public const VALIDATION_SERVICE = 'nin.validation';

    protected $table = 'validation';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oldBal' => 'float',
            'newBal' => 'float',
            'price' => 'float',
            'refundAmount' => 'float',
            'refundedAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Instant NIN lookups only (by NIN, phone or demographics).
     */
    public function scopeVerifications(Builder $query): Builder
    {
        return $query->whereIn('service', self::VERIFY_SERVICES);
    }

    /**
     * Filed NIN validations only — the multi-day NIMC job.
     */
    public function scopeValidations(Builder $query): Builder
    {
        return $query->where('service', self::VALIDATION_SERVICE);
    }

    /**
     * Decode the stored provider payload.
     */
    public function getParsedResult(): ?array
    {
        if (! $this->result) {
            return null;
        }

        $decoded = json_decode($this->result, true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function generateReference(): string
    {
        return 'NIN_'.strtoupper(uniqid()).random_int(1000, 9999);
    }
}
