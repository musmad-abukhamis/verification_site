<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnattributedPayment;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Admin > Wallet > Unattributed Payments.
 *
 * Funding payments that arrived without a matching user -- typically a transfer
 * into a virtual account whose `accountkyc` row is missing, or a payment whose
 * customer email does not match any account. Each one is real money already
 * received, so the list is a reconciliation queue, not a log.
 */
class UnattributedPaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', UnattributedPayment::STATUS_PENDING);

        $query = UnattributedPayment::query()->with(['resolvedUser', 'resolvedBy']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $payments = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (UnattributedPayment $p) => [
                'id' => $p->id,
                'provider' => $p->provider,
                'reference' => $p->reference,
                'account_number' => $p->account_number,
                'customer_email' => $p->customer_email,
                'customer_name' => $p->customer_name,
                'amount' => (float) $p->amount,
                'settlement_amount' => $p->settlement_amount !== null ? (float) $p->settlement_amount : null,
                'status' => $p->status,
                'note' => $p->note,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
                'resolved_at' => $p->resolved_at?->format('Y-m-d H:i'),
                'resolved_user' => $p->resolvedUser ? [
                    'id' => $p->resolvedUser->id,
                    'username' => $p->resolvedUser->username,
                ] : null,
                'resolved_by' => $p->resolvedBy?->username,
            ])
            ->withQueryString();

        // Same server-side user picker the Account Funding screen uses.
        $q = trim((string) $request->input('q', ''));
        $userResults = [];

        if (mb_strlen($q) >= 2) {
            $userResults = User::query()
                ->where(function ($query) use ($q) {
                    $query->where('username', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                })
                ->orderBy('username')
                ->limit(15)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'name' => $u->name,
                    'email' => $u->email,
                    'balance' => (float) $u->balance,
                ])
                ->all();
        }

        return Inertia::render('Admin/Wallet/Unattributed', [
            'payments' => $payments,
            'filters' => ['status' => $status, 'search' => $search],
            'userResults' => $userResults,
            'q' => $q,
            'counts' => [
                'pending' => UnattributedPayment::where('status', UnattributedPayment::STATUS_PENDING)->count(),
                'pending_amount' => (float) UnattributedPayment::where('status', UnattributedPayment::STATUS_PENDING)->sum('amount'),
            ],
        ]);
    }

    /**
     * Credit the payment to a user and close it out.
     *
     * The ledger row is keyed on the provider reference -- the same id the
     * webhook would have used -- so if the provider ever redelivers this
     * payment, the webhook's ledger check short-circuits and cannot double
     * credit.
     */
    public function resolve(Request $request, UnattributedPayment $unattributedPayment)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'amount' => 'nullable|numeric|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        if ($unattributedPayment->status !== UnattributedPayment::STATUS_PENDING) {
            return back()->withErrors(['username' => 'This payment has already been dealt with.']);
        }

        $user = User::where('username', $validated['username'])->first();

        if (! $user) {
            return back()->withErrors(['username' => 'User not found.']);
        }

        // Defence in depth: if a ledger row already carries this reference the
        // money has been credited by some other path, and crediting again would
        // duplicate it.
        if (WalletHistory::whereKey($unattributedPayment->reference)->exists()) {
            $unattributedPayment->update([
                'status' => UnattributedPayment::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
                'note' => 'Already present in the ledger; marked resolved without crediting again.',
            ]);

            return back()->withErrors([
                'username' => 'This reference is already in the wallet ledger. Marked resolved; nothing was credited.',
            ]);
        }

        $amount = (float) ($validated['amount'] ?? $unattributedPayment->amount);

        DB::transaction(function () use ($user, $amount, $unattributedPayment, $request, $validated) {
            $user->credit($amount, false, [
                'id' => $unattributedPayment->reference,
                'fundingtype' => 'automatic-funding',
            ]);

            $unattributedPayment->update([
                'status' => UnattributedPayment::STATUS_RESOLVED,
                'resolved_user_id' => $user->id,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'note' => $validated['note'] ?? null,
            ]);
        });

        Log::info('Unattributed payment resolved', [
            'reference' => $unattributedPayment->reference,
            'creditedUserId' => $user->id,
            'amount' => $amount,
            'byAdminId' => $request->user()->id,
        ]);

        return back()->with('success', '₦'.number_format($amount, 2).' credited to '.$user->username.'.');
    }

    /**
     * Close a payment without crediting anyone (a duplicate, a test transfer,
     * a refund handled at the provider). Reversible via reopen.
     */
    public function ignore(Request $request, UnattributedPayment $unattributedPayment)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:500',
        ]);

        if ($unattributedPayment->status === UnattributedPayment::STATUS_RESOLVED) {
            return back()->withErrors(['note' => 'This payment was already credited and cannot be ignored.']);
        }

        $unattributedPayment->update([
            'status' => UnattributedPayment::STATUS_IGNORED,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'note' => $validated['note'],
        ]);

        return back()->with('success', 'Payment marked as ignored.');
    }

    /**
     * Put an ignored payment back in the queue. Credited ones stay closed --
     * the money has moved and reversing it is a wallet debit, done elsewhere.
     */
    public function reopen(UnattributedPayment $unattributedPayment)
    {
        if ($unattributedPayment->status !== UnattributedPayment::STATUS_IGNORED) {
            return back()->withErrors(['status' => 'Only ignored payments can be reopened.']);
        }

        $unattributedPayment->update([
            'status' => UnattributedPayment::STATUS_PENDING,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        return back()->with('success', 'Payment reopened.');
    }
}
