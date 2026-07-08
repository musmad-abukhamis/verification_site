<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Notifications — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/notifications: create/edit/toggle/delete
 * global or user-specific notifications and view summary stats.
 */
class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn (Notification $n) => $this->payload($n));

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'stats' => [
                'total' => Notification::count(),
                'active' => Notification::where('isEnabled', true)
                    ->where(fn ($q) => $q->whereNull('expiresAt')->orWhere('expiresAt', '>', now()))
                    ->count(),
                'expired' => Notification::whereNotNull('expiresAt')->where('expiresAt', '<', now())->count(),
                'global' => Notification::whereNull('userId')->count(),
                'userSpecific' => Notification::whereNotNull('userId')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Notification::create($data);

        return back()->with('success', 'Notification created successfully.');
    }

    public function update(Request $request, Notification $notification)
    {
        $data = $this->validated($request);

        $notification->update($data);

        return back()->with('success', 'Notification updated successfully.');
    }

    public function toggle(Notification $notification)
    {
        $notification->update(['isEnabled' => ! $notification->isEnabled]);

        return back()->with('success', 'Notification '.($notification->isEnabled ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();

        return back()->with('success', 'Notification deleted successfully.');
    }

    /**
     * Validate + normalise the create/update payload (blank userId → global).
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|min:2',
            'message' => 'required|string|min:5',
            'isEnabled' => 'boolean',
            'duration' => 'nullable|integer|min:1',
            'expiresAt' => 'nullable|date',
            'userId' => 'nullable|string',
        ]);

        $userId = $validated['userId'] ?? null;
        $userId = ($userId === '' || $userId === null) ? null : $userId;

        if ($userId !== null && ! User::whereKey($userId)->exists()) {
            abort(422, 'The specified user does not exist.');
        }

        return [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'isEnabled' => $validated['isEnabled'] ?? true,
            'duration' => $validated['duration'] ?? null,
            'expiresAt' => ! empty($validated['expiresAt']) ? Carbon::parse($validated['expiresAt']) : null,
            'userId' => $userId,
        ];
    }

    private function payload(Notification $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'isEnabled' => (bool) $n->isEnabled,
            'duration' => $n->duration,
            'userId' => $n->userId,
            'is_global' => $n->userId === null,
            'expiresAt' => optional($n->expiresAt)->toIso8601String(),
            'createdAt' => optional($n->createdAt)->toIso8601String(),
        ];
    }
}
