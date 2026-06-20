<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BvnModificationPricing;
use App\Http\Controllers\Controller;
use App\Models\BvnModification;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN Modification — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/bvn-modifications: review user requests,
 * update their status (pending / modified / rejected / picked) with an optional
 * comment, and delete requests.
 */
class BvnModificationController extends Controller
{
    use BvnModificationPricing;

    private const STATUSES = ['pending', 'modified', 'rejected', 'picked'];

    /**
     * List all requests with filters + summary stats.
     */
    public function index(Request $request)
    {
        $query = BvnModification::query()->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bvn', 'like', "%{$search}%")
                    ->orWhere('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
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

        if (($serviceType = $request->input('serviceType')) && $serviceType !== 'all') {
            $query->where('serviceType', $serviceType);
        }

        if ($dateFrom = $request->input('dateFrom')) {
            $query->whereDate('createdAt', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('dateTo')) {
            $query->whereDate('createdAt', '<=', $dateTo);
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(15)
            ->through(fn (BvnModification $r) => [
                'id' => $r->id,
                'bvn' => $r->bvn,
                'nin' => $r->nin,
                'serviceType' => $r->serviceType,
                'service_label' => $this->serviceLabel($r->serviceType),
                'status' => $r->status,
                'comment' => $r->comment,
                'amount_charged' => $r->amountCharged,
                'created_at' => $r->createdAt?->format('Y-m-d H:i'),
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'username' => $r->user->username,
                    'email' => $r->user->email,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Admin/BvnModifications/Index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status', 'serviceType', 'dateFrom', 'dateTo']),
            'statuses' => self::STATUSES,
            'serviceTypes' => array_map(fn ($t) => [
                'value' => $t,
                'label' => $this->serviceLabel($t),
            ], $this->serviceTypes()),
            'stats' => [
                'total' => BvnModification::count(),
                'pending' => BvnModification::where('status', 'pending')->count(),
                'modified' => BvnModification::where('status', 'modified')->count(),
                'rejected' => BvnModification::where('status', 'rejected')->count(),
                'picked' => BvnModification::where('status', 'picked')->count(),
            ],
        ]);
    }

    /**
     * Show a single request with full modification details.
     */
    public function show(BvnModification $modification)
    {
        $modification->load('user');

        return Inertia::render('Admin/BvnModifications/Show', [
            'request' => $this->detailPayload($modification),
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Update a request's status (+ optional comment).
     */
    public function updateStatus(Request $request, BvnModification $modification)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', self::STATUSES),
            'comment' => 'nullable|string',
        ]);

        if ($validated['status'] === 'rejected' && empty(trim($validated['comment'] ?? ''))) {
            return back()->withErrors(['message' => 'A comment is required when rejecting a request.']);
        }

        $modification->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? $modification->comment,
        ]);

        return back()->with('success', 'Request status updated successfully.');
    }

    /**
     * Delete a request.
     */
    public function destroy(BvnModification $modification)
    {
        $modification->delete();

        return redirect()->route('admin.bvn-modifications.index')
            ->with('success', 'Request deleted successfully.');
    }
}
