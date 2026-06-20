<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: validation (table "validation", integer auto-increment id).
 *
 * Stores NIN verification + validation records. `result` holds the raw provider
 * JSON payload (used to render downloadable slips), `comment` is a human label.
 */
class Validation extends Model
{
    protected $table = 'validation';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oldBal' => 'float',
            'newBal' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
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
