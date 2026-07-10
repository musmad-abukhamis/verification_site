<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Prisma model: Transactions (table "Transactions").
 *
 * The id doubles as the human-facing reference, `price` is the amount and
 * `response` stores the provider/gateway message (JSON for service records).
 */
class Transaction extends Model
{
    use HasPrismaId;

    protected $table = 'Transactions';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    // Transaction type constants (kept for call-site compatibility).
    const TYPE_NIN_VERIFICATION = 'nin_verification';

    const TYPE_NIN_SLIP_DOWNLOAD = 'nin_slip_download';

    const TYPE_NIN_IPE = 'nin_ipe';

    const TYPE_NIN_VALIDATION = 'nin_validation';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'oldbal' => 'float',
            'newbal' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Generate a unique transaction reference (used as the primary key).
     */
    public static function generateReference(string $prefix = 'TXN'): string
    {
        return $prefix.'_'.strtoupper(Str::random(10)).random_int(1000, 9999);
    }

    /* ----------------------------------------------------------------------
     | Compatibility accessors — the old Transaction exposed reference/amount/
     | details columns; these map onto the new id/price/response columns.
     | ---------------------------------------------------------------------- */

    public function getReferenceAttribute(): string
    {
        return (string) $this->id;
    }

    public function getAmountAttribute(): float
    {
        return (float) $this->price;
    }

    /**
     * Decode the JSON stored in `response` back into the old `details` array.
     */
    public function getDetailsAttribute(): array
    {
        $decoded = json_decode((string) $this->response, true);

        return is_array($decoded) ? $decoded : [];
    }

    /* ----------------------------------------------------------------------
     | Factory helpers for service records.
     | ---------------------------------------------------------------------- */

    public static function createVerification(string $userId, float $amount, string $type, array $details = []): self
    {
        return static::createServiceRecord($userId, $amount, $type, $details, 'NIN');
    }

    public static function createSlipDownload(string $userId, float $amount, array $details = []): self
    {
        return static::createServiceRecord($userId, $amount, self::TYPE_NIN_SLIP_DOWNLOAD, $details, 'NIN');
    }

    protected static function createServiceRecord(string $userId, float $amount, string $type, array $details, string $network): self
    {
        $currentBalance = (float) (Auth::user()?->balance ?? 0);

        return static::create([
            'id' => $details['reference'] ?? static::generateReference(strtoupper(str_replace('nin_', '', $type))),
            'network' => $network,
            'name' => $details['slip_name'] ?? ucwords(str_replace('_', ' ', $type)),
            'price' => (int) round($amount),
            'type' => $type,
            'phone' => (string) ($details['nin'] ?? $details['phone'] ?? ''),
            'oldbal' => (float) ($details['old_balance'] ?? $currentBalance),
            'newbal' => (float) ($details['new_balance'] ?? $currentBalance),
            'status' => $details['status'] ?? 'success',
            'userId' => $userId,
            'response' => json_encode($details),
        ]);
    }

    public function markAsSuccess(?string $providerReference = null, ?string $message = null): self
    {
        $this->forceFill([
            'status' => 'success',
            'response' => $message ?? $providerReference ?? $this->response ?? 'success',
        ])->save();

        return $this;
    }

    public function markAsFailed(?string $message = null): self
    {
        $this->forceFill([
            'status' => 'failed',
            'response' => $message ?? $this->response ?? 'failed',
        ])->save();

        return $this;
    }
}
