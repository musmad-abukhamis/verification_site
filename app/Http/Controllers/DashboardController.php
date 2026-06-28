<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $recentTransactions = Transaction::where('userId', $user->id)
            ->orderByDesc('createdAt')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'reference' => $transaction->id,
                'name' => $transaction->name,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->price,
                'date' => $transaction->createdAt?->format('M d, Y H:i'),
            ]);

        $balance = (float) $user->balance;

        return Inertia::render('Dashboard', [
            'wallet' => [
                'balance' => $balance,
                'bonus_balance' => 0.0,
                'total_balance' => $balance,
            ],
            'recent_transactions' => $recentTransactions,
            'reserved_accounts' => $user->reservedAccounts(),
        ]);
    }
}
