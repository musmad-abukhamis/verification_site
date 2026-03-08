<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\VerificationLog;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Statistics
        $stats = [
            'total_users' => User::count(),
            'total_transactions' => Transaction::count(),
            'total_revenue' => Transaction::where('status', 'success')->sum('amount'),
            'pending_transactions' => Transaction::where('status', 'pending')->count(),
            'total_verifications' => VerificationLog::where('status', 'verified')->count(),
            'total_wallet_balance' => Wallet::sum('balance'),
        ];

        // Recent transactions
        $recentTransactions = Transaction::with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'user' => $t->user?->name ?? 'Unknown',
                'type' => $t->type,
                'amount' => $t->amount,
                'status' => $t->status,
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ]);

        // Recent users
        $recentUsers = User::latest()
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => $u->is_admin,
                'created_at' => $u->created_at->format('Y-m-d H:i'),
            ]);

        // Transaction chart data (last 30 days)
        $chartData = Transaction::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'success')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'recentUsers' => $recentUsers,
            'chartData' => $chartData,
        ]);
    }
}
