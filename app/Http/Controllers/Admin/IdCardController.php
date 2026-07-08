<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdCard;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * ID Card applications — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/idcard: review user ID-card applications,
 * update their status (pending / approved / rejected) with an optional comment,
 * view/download the submitted passport photo, and delete applications.
 */
class IdCardController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * List all applications with filters + summary stats.
     */
    public function index(Request $request)
    {
        $query = IdCard::query()->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('agentId', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(15)
            ->through(fn (IdCard $r) => [
                'id' => $r->id,
                'fullname' => $r->fullname,
                'email' => $r->email,
                'agentId' => $r->agentId,
                'status' => $r->status,
                'comment' => $r->comment,
                'old_balance' => $r->oldBalance,
                'new_balance' => $r->newBalance,
                'amount_charged' => $r->amountCharged,
                'created_at' => $r->createdAt?->format('Y-m-d H:i'),
                'image_url' => route('idcard.image', $r->id),
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'username' => $r->user->username,
                    'email' => $r->user->email,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Admin/IdCard/Index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status']),
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => IdCard::count(),
                'pending' => IdCard::where('status', 'pending')->count(),
                'approved' => IdCard::where('status', 'approved')->count(),
                'rejected' => IdCard::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * Update an application's status (+ optional comment).
     */
    public function updateStatus(Request $request, IdCard $idCard)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', self::STATUSES),
            'comment' => 'nullable|string',
        ]);

        if ($validated['status'] === 'rejected' && empty(trim($validated['comment'] ?? ''))) {
            return back()->withErrors(['message' => 'A comment is required when rejecting a request.']);
        }

        $idCard->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? $idCard->comment,
        ]);

        return back()->with('success', 'Application status updated successfully.');
    }

    /**
     * Delete an application.
     */
    public function destroy(IdCard $idCard)
    {
        $idCard->delete();

        return redirect()->route('admin.idcard.index')
            ->with('success', 'Application deleted successfully.');
    }
}
