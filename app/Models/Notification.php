<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prisma model: Notification (table "notifications").
 */
class Notification extends Model
{
    use HasPrismaId;

    protected $table = 'notifications';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'isEnabled' => 'boolean',
            'duration' => 'integer',
            'expiresAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function users(): HasMany
    {
        return $this->hasMany(NotificationUser::class, 'notificationId');
    }
}
