<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundingSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin Wallet section -- the central "Account Funding" form (a port of nimcweb
 * admin/walletmgt). Funding writes through the User wallet helpers: balance on
 * the user, plus a `wallethistory` ledger row.
 *
 * Browsing that ledger lives on the history screens
 * ({@see \App\Http\Controllers\Admin\HistoryController::wallet}), which split it
 * into funding, debits and refunds rather than one list behind a type dropdown.
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
            'fundingSettings' => [
                'credit_net_of_fees' => FundingSetting::creditsNetOfFees(),
            ],
        ]);
    }

    /**
     * Choose what a funding payment credits: the gross amount the customer
     * sent (we absorb the provider fee), or the settlement amount actually
     * received (the user absorbs it).
     */
    public function updateFundingSettings(Request $request)
    {
        $validated = $request->validate([
            'credit_net_of_fees' => 'required|boolean',
        ]);

        $settings = FundingSetting::current();
        $settings->credit_net_of_fees = $validated['credit_net_of_fees'];
        $settings->save();

        return back()->with('success', $validated['credit_net_of_fees']
            ? 'Funding will now credit the amount received after provider fees.'
            : 'Funding will now credit the full amount the customer sends.');
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
}
