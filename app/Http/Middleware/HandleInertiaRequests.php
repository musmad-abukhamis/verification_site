<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'balance' => (float) $user->balance,
                    'role' => $user->role?->value,
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'verification_data' => fn () => $request->session()->get('verification_data'),
            ],
            // Powers the notification bell/drawer and global-announcement modal.
            // Lazily evaluated so it only runs when a page (or partial reload)
            // actually requests it.
            'notifications' => fn () => $this->notificationsPayload($request),
        ];
    }

    /**
     * The current user's active notifications (global + user-specific), shaped
     * for the bell/drawer, plus the unread count and the latest global
     * announcement for the modal. Null when unauthenticated.
     *
     * A single query drives all three: enabled, not-expired notifications
     * addressed to this user (or global), with the user's read/dismiss pivot
     * eager-loaded; dismissed ones are filtered out.
     */
    private function notificationsPayload(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $items = Notification::query()
            ->where('isEnabled', true)
            ->where(function ($q) {
                $q->whereNull('expiresAt')->orWhere('expiresAt', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->whereNull('userId')->orWhere('userId', $user->id);
            })
            ->with(['users' => fn ($q) => $q->where('userId', $user->id)])
            ->orderByDesc('createdAt')
            ->get()
            ->map(function (Notification $n) {
                $pivot = $n->users->first();

                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'is_global' => $n->userId === null,
                    'duration' => $n->duration,
                    'expiresAt' => optional($n->expiresAt)->toIso8601String(),
                    'createdAt' => optional($n->createdAt)->toIso8601String(),
                    'isRead' => (bool) ($pivot?->isRead),
                    'isDismissed' => (bool) ($pivot?->isDismissed),
                ];
            })
            ->reject(fn ($n) => $n['isDismissed'])
            ->values();

        return [
            'items' => $items,
            'unread' => $items->where('isRead', false)->count(),
            'latest_global' => $items->firstWhere('is_global', true),
        ];
    }
}
