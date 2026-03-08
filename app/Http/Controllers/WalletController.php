<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WalletController extends Controller
{
    /**
     * Show wallet dashboard
     */
    public function index()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $recentTransactions = Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
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

        return Inertia::render('Wallet/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Show fund wallet page
     */
    public function fund()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        return Inertia::render('Wallet/Fund', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'paystack_key' => config('services.paystack.public_key'),
        ]);
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request)
    {
        $userId = Auth::id();

        $transactions = Transaction::where('user_id', $userId)
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'fee' => (float) $transaction->fee,
                    'total_amount' => (float) $transaction->total_amount,
                    'details' => $transaction->details,
                    'date' => $transaction->created_at->format('M d, Y H:i'),
                ];
            });

        return Inertia::render('Wallet/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'status']),
        ]);
    }
}
