<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: NotificationUser (table "notification_users").
 */
class NotificationUser extends Model
{
    use HasPrismaId;

    protected $table = 'notification_users';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'isRead' => 'boolean',
            'isDismissed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notificationId');
    }
}
