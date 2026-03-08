<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $recentTransactions = Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->created_at->format('M d, Y H:i'),
                ];
            });

        return Inertia::render('Dashboard', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
