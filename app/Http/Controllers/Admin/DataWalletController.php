<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\WalletLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin manual wallet adjustments through the data-module ledger (wallet_entries).
 * User search uses Postgres ILIKE.
 */
class DataWalletController extends Controller
{
    public function index(Request $request)
    {
        $users = collect();

        if ($search = $request->input('search')) {
            $users = User::query()
                ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%"))
                ->limit(20)
                ->get(['id', 'name', 'username', 'email', 'phone', 'balance'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'balance' => (float) $u->balance,
                ]);
        }

        return Inertia::render('Admin/DataWallet/Index', [
            'filters' => $request->only('search'),
            'users' => $users,
            'recentEntries' => WalletEntry::with('user:id,name,username')
                ->latest('created_at')
                ->limit(15)
                ->get()
                ->map(fn (WalletEntry $e) => [
                    'id' => $e->id,
                    'user' => $e->user?->name ?? $e->user?->username,
                    'direction' => $e->direction,
                    'amount' => (float) $e->amount,
                    'balance_after' => (float) $e->balance_after,
                    'reason' => $e->reason,
                    'at' => $e->created_at?->format('M d, Y H:i'),
                ]),
        ]);
    }

    public function credit(Request $request, User $user, WalletLedger $ledger)
    {
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        $ledger->credit($user, (float) $validated['amount'], 'admin_credit');

        return back()->with('success', '₦'.number_format($validated['amount']).' credited to '.($user->name ?? $user->username).'.');
    }

    public function debit(Request $request, User $user, WalletLedger $ledger)
    {
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        if ($ledger->debit($user, (float) $validated['amount'], 'admin_debit') === false) {
            return back()->withErrors(['amount' => 'Insufficient balance. Current: ₦'.number_format((float) $user->balance)]);
        }

        return back()->with('success', '₦'.number_format($validated['amount']).' debited from '.($user->name ?? $user->username).'.');
    }
}
