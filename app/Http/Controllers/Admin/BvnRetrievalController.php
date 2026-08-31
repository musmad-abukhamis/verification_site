<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BvnRetrieval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * BVN Retrieval — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/bvn-retrieval: review retrieval requests,
 * fill in the retrieved BVN, set status (pending / processing / completed /
 * rejected) with an optional comment, and delete requests.
 */
class BvnRetrievalController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'completed', 'rejected'];

    private function present(BvnRetrieval $r): array
    {
        return [
            'id' => $r->id,
            'firstname' => $r->firstname,
            'middlename' => $r->middlename,
            'surname' => $r->surname,
            'phone' => $r->phone,
            'retrievalType' => $r->retrievalType,
            'ticketId1' => $r->ticketId1,
            'ticketId2' => $r->ticketId2,
            'batchId' => $r->batchId,
            'nin' => $r->nin,
            'bvn' => $r->bvn,
            'status' => $r->status,
            'comment' => $r->comment,
            'old_balance' => $r->oldBal,
            'new_balance' => $r->newBal,
            'charged_amount' => $r->chargedAmount(),
            'is_refundable' => $r->isRefundable(),
            'refunded_at' => $r->refundedAt?->format('Y-m-d H:i'),
            'refund_amount' => $r->refundAmount !== null ? (float) $r->refundAmount : null,
            'created_at' => $r->createdAt?->format('Y-m-d H:i'),
            'updated_at' => $r->updatedAt?->format('Y-m-d H:i'),
            'user' => $r->user ? [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'username' => $r->user->username,
                'email' => $r->user->email,
                'phone' => $r->user->phone,
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        $query = BvnRetrieval::query()->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticketId1', 'like', "%{$search}%")
                    ->orWhere('ticketId2', 'like', "%{$search}%")
                    ->orWhere('batchId', 'like', "%{$search}%")
                    ->orWhere('nin', 'like', "%{$search}%")
                    ->orWhere('bvn', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(15)
            ->through(fn (BvnRetrieval $r) => $this->present($r))
            ->withQueryString();

        return Inertia::render('Admin/BvnRetrievals/Index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status']),
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => BvnRetrieval::count(),
                'pending' => BvnRetrieval::where('status', 'pending')->count(),
                'processing' => BvnRetrieval::where('status', 'processing')->count(),
                'completed' => BvnRetrieval::where('status', 'completed')->count(),
                'rejected' => BvnRetrieval::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * Update a request: set the retrieved BVN, status and comment.
     */
    public function update(Request $request, BvnRetrieval $retrieval)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'bvn' => 'nullable|string',
            'comment' => 'nullable|string',
        ]);

        // BVN is required (and must be 11 digits) once the request is completed.
        if ($validated['status'] === 'completed' && ! preg_match('/^\d{11}$/', (string) ($validated['bvn'] ?? ''))) {
            return back()->withErrors(['message' => 'BVN must be exactly 11 digits when status is completed.']);
        }

        $retrieval->update([
            'bvn' => $validated['bvn'] ?? $retrieval->bvn,
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'Request updated successfully.');
    }

    /**
     * Return the retrieval fee for a request that could not be fulfilled.
     *
     * Deliberately separate from {@see update()}: setting a request to
     * `rejected` records an outcome, this moves money, and the two do not
     * always go together — a request can be rejected and refunded on different
     * days, and a completed one can still owe a partial refund.
     *
     * The amount is editable (a part-delivered retrieval is a real thing) but
     * capped at what the user paid, and `refundedAt` makes it once-only. Without
     * that guard the same row could be refunded twice by two admins, with
     * nothing on it to show the first one happened.
     */
    public function refund(Request $request, BvnRetrieval $retrieval)
    {
        $charged = $retrieval->chargedAmount();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max($charged, 0.01)],
            'reason' => 'nullable|string|max:255',
        ]);

        if (! $retrieval->isRefundable()) {
            return back()->withErrors([
                'message' => $retrieval->refundedAt
                    ? 'This request was already refunded on '.$retrieval->refundedAt->format('d M Y H:i').'.'
                    : 'There is nothing to refund on this request.',
            ]);
        }

        $user = $retrieval->user;

        if (! $user) {
            return back()->withErrors(['message' => 'The user for this request no longer exists.']);
        }

        $amount = round((float) $validated['amount'], 2);

        // The credit and the stamp go together: a crash between them would
        // either pay twice on retry or lose the record of having paid.
        DB::transaction(function () use ($retrieval, $user, $amount, $validated) {
            $user->credit($amount, false, [
                'fundingtype' => 'refund',
                'status' => 'refund',
            ]);

            $retrieval->update([
                'refundedAt' => now(),
                'refundAmount' => $amount,
                // `nullable` omits the key entirely when the field is absent,
                // so this cannot read $validated['reason'] directly.
                'comment' => ($validated['reason'] ?? null)
                    ? 'Refunded: '.$validated['reason']
                    : $retrieval->comment,
            ]);
        });

        Log::info('[admin] BVN retrieval refunded', [
            'retrieval_id' => $retrieval->id,
            'amount' => $amount,
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', '₦'.number_format($amount, 2).' refunded to '.$user->name.'.');
    }

    public function destroy(BvnRetrieval $retrieval)
    {
        $retrieval->delete();

        return redirect()->route('admin.bvn-retrievals.index')
            ->with('success', 'Request deleted successfully.');
    }
}
