<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationUser;
use Illuminate\Support\Facades\Auth;

/**
 * Notifications — user side.
 *
 * Port of the nimcweb markNotificationAsRead / dismissNotification server
 * actions. The list, unread count and global-announcement modal are delivered
 * as an Inertia shared prop (see HandleInertiaRequests::notificationsPayload);
 * these endpoints just record per-user read/dismiss state.
 */
class NotificationController extends Controller
{
    public function read(Notification $notification)
    {
        $this->pivot($notification, ['isRead' => true]);

        return back();
    }

    public function dismiss(Notification $notification)
    {
        $this->pivot($notification, ['isRead' => true, 'isDismissed' => true]);

        return back();
    }

    /**
     * Upsert the current user's pivot row for a notification they may see
     * (their own or a global one).
     */
    private function pivot(Notification $notification, array $state): void
    {
        $userId = Auth::id();

        if ($notification->userId !== null && $notification->userId !== $userId) {
            abort(403);
        }

        NotificationUser::updateOrCreate(
            ['userId' => $userId, 'notificationId' => $notification->id],
            $state,
        );
    }
}
