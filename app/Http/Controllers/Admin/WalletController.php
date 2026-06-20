<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin Wallet section.
 *
 * Port of nimcweb admin/walletmgt (central "Account Funding" form) and
 * admin/wallettransactions (wallet history list). Funding writes through the
 * User wallet helpers (balance on the user + a `wallethistory` ledger row).
 */
class WalletController extends Controller
{
    /**
     * Account Funding page. Returns matching users for the searchable picker
     * (server-side search via the `q` query param, Inertia partial reload).
     */
    public function index(Request $request)
    {
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

        return Inertia::render('Admin/Wallet/Index', [
            'userResults' => $userResults,
            'q' => $q,
        ]);
    }

    /**
     * Credit or debit a user's wallet by username.
     */
    public function fund(Request $request)
    {
        $validated = $request->validate([
            'option' => 'required|in:Credit,Debit',
            'username' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $user = User::where('username', $validated['username'])->first();
        if (! $user) {
            return back()->withErrors(['username' => 'User not found.']);
        }

        $amount = (float) $validated['amount'];

        if ($validated['option'] === 'Credit') {
            $user->credit($amount, false, ['fundingtype' => 'manual-funding']);

            return back()->with('success', '₦'.number_format($amount, 2).' credited to '.$user->username.'.');
        }

        if ((float) $user->balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient balance. Current balance: ₦'.number_format((float) $user->balance, 2)]);
        }

        $user->debit($amount, false, ['fundingtype' => 'manual-funding']);

        return back()->with('success', '₦'.number_format($amount, 2).' debited from '.$user->username.'.');
    }

    /**
     * Wallet transaction (history) list with summary stats + filters.
     */
    public function transactions(Request $request)
    {
        $base = WalletHistory::query();

        $query = (clone $base)->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('fundingtype', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($type = $request->input('type')) && $type !== 'all') {
            $query->where('type', $type);
        }

        $transactions = $query
            ->orderByDesc('createdAt')
            ->paginate(15)
            ->through(fn (WalletHistory $w) => [
                'id' => $w->id,
                'type' => $w->type,
                'fundingtype' => $w->fundingtype,
                'status' => $w->status,
                'amount' => (float) $w->amount,
                'old_balance' => (float) $w->oldbal,
                'new_balance' => (float) $w->newbal,
                'created_at' => $w->createdAt?->format('Y-m-d H:i'),
                'user' => $w->user ? [
                    'id' => $w->user->id,
                    'username' => $w->user->username,
                    'name' => $w->user->name,
                    'email' => $w->user->email,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('Admin/Wallet/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'type']),
            'stats' => [
                'total_credit' => (float) (clone $base)->where('type', 'credit')->sum('amount'),
                'total_debit' => (float) (clone $base)->where('type', 'debit')->sum('amount'),
                'total_count' => (clone $base)->count(),
            ],
        ]);
    }
}
