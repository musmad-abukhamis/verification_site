<?php

namespace App\Http\Controllers;

use App\Models\WalletHistory;
use App\Services\Wallet\PayVesselService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WalletController extends Controller
{
    /**
     * Wallet dashboard: balance + the most recent ledger movements.
     */
    public function index()
    {
        $user = Auth::user();

        $recent = WalletHistory::where('userId', $user->id)
            ->orderByDesc('createdAt')
            ->limit(10)
            ->get()
            ->map(fn (WalletHistory $w) => $this->presentHistory($w));

        return Inertia::render('Wallet/Index', [
            'wallet' => $this->walletPayload($user),
            'recent_transactions' => $recent,
            'reserved_accounts' => $user->reservedAccounts(),
        ]);
    }

    /**
     * Fund Wallet — virtual-account management (Billstack reserved accounts).
     */
    public function fund()
    {
        $user = Auth::user();

        return Inertia::render('Wallet/Fund', [
            'wallet' => $this->walletPayload($user),
            'reserved_accounts' => $user->reservedAccounts(),
        ]);
    }

    /**
     * Create the user's static virtual accounts (PayVessel).
     *
     * BVN is the only input: PayVessel takes one name field, which we fill from
     * the user's registered name, and a single request issues both the PalmPay
     * and 9PSB accounts.
     */
    public function createVirtualAccount(Request $request, PayVesselService $payvessel)
    {
        $validated = $request->validate([
            'bvn' => 'required|digits:11',
        ]);

        $result = $payvessel->createAccounts(Auth::user(), $validated['bvn']);

        if (! $result['success']) {
            return back()->withErrors(['bvn' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * "My Wallet Transactions" — the wallethistory ledger with stats + filters.
     */
    public function transactions(Request $request)
    {
        $userId = Auth::id();
        $base = WalletHistory::where('userId', $userId);

        $query = clone $base;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('fundingtype', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
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
            ->through(fn (WalletHistory $w) => $this->presentHistory($w))
            ->withQueryString();

        return Inertia::render('Wallet/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'type']),
            'wallet' => $this->walletPayload(Auth::user()),
            'stats' => [
                'total_credit' => (float) (clone $base)->where('type', 'credit')->sum('amount'),
                'total_debit' => (float) (clone $base)->where('type', 'debit')->sum('amount'),
                'total_count' => (clone $base)->count(),
            ],
        ]);
    }

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    private function presentHistory(WalletHistory $w): array
    {
        return [
            'id' => $w->id,
            'reference' => $w->id,
            'type' => $w->type,                 // direction: credit | debit
            'fundingtype' => $w->fundingtype,   // source tag
            'status' => $w->status,
            'amount' => (float) $w->amount,
            'old_balance' => (float) $w->oldbal,
            'new_balance' => (float) $w->newbal,
            'date' => $w->createdAt?->format('M d, Y H:i'),
        ];
    }
}
