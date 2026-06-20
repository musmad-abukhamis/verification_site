<?php

namespace App\Http\Controllers;

use App\Models\AccountKyc;
use App\Models\WalletHistory;
use App\Services\Wallet\BillstackService;
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
            'reserved_accounts' => $this->reservedAccounts($user->id),
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
            'reserved_accounts' => $this->reservedAccounts($user->id),
        ]);
    }

    /**
     * Create a virtual account (with BVN KYC) for the authenticated user.
     */
    public function createVirtualAccount(Request $request, BillstackService $billstack)
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:50',
            'lastName' => 'required|string|max:50',
            'bvn' => 'required|digits:11',
            'bank' => 'nullable|in:9PSB,SAFEHAVEN,PROVIDUS,BANKLY,PALMPAY',
        ]);

        $result = $billstack->createAccountWithKYC(
            Auth::user(),
            $validated['firstName'],
            $validated['lastName'],
            $validated['bvn'],
            $validated['bank'] ?? 'PALMPAY',
        );

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

    /**
     * Build the user's reserved virtual accounts from their accountkyc row.
     * Mirrors nimcweb's GetReservedAccounts (skips empty / "0" columns).
     */
    private function reservedAccounts(string $userId): array
    {
        $account = AccountKyc::where('userId', $userId)->first();

        if (! $account) {
            return [];
        }

        $banks = [
            'palmpay' => ['label' => 'PalmPay Bank', 'name' => 'name'],
            'palmpay2' => ['label' => 'PalmPay Business', 'name' => 'palmpay2_name'],
            'moniepoint' => ['label' => 'Moniepoint Bank', 'name' => 'name'],
            'wema' => ['label' => 'Wema Bank', 'name' => 'wema_name'],
            'providus' => ['label' => 'Providus Bank', 'name' => 'name'],
            'sterling' => ['label' => 'Sterling Bank', 'name' => 'name'],
            'opay' => ['label' => 'Opay Bank', 'name' => 'name'],
            'fidelity' => ['label' => 'Fidelity Bank', 'name' => 'name'],
            'Ninesp' => ['label' => '9PSB Bank', 'name' => 'ninesp_name'],
        ];

        $accounts = [];

        foreach ($banks as $column => $meta) {
            $number = $account->{$column} ?? null;

            if ($number && $number !== '0') {
                $accounts[] = [
                    'bank' => $meta['label'],
                    'account_number' => $number,
                    'account_name' => $account->{$meta['name']} ?: ($account->name ?: 'Account'),
                ];
            }
        }

        return $accounts;
    }
}
