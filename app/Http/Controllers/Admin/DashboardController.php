<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Validation;
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
            'total_revenue' => (float) Transaction::where('status', 'success')->sum('price'),
            'pending_transactions' => Transaction::where('status', 'pending')->count(),
            'total_verifications' => Validation::where('status', 'completed')->count(),
            'total_wallet_balance' => (float) User::sum('balance'),
        ];

        // Recent transactions
        $recentTransactions = Transaction::with('user')
            ->latest('createdAt')
            ->limit(10)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'user' => $t->user?->name ?? 'Unknown',
                'type' => $t->type,
                'amount' => (float) $t->price,
                'status' => $t->status,
                'created_at' => $t->createdAt?->format('Y-m-d H:i'),
            ]);

        // Recent users
        $recentUsers = User::latest('createdAt')
            ->limit(10)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => $u->isAdmin(),
                'created_at' => $u->createdAt?->format('Y-m-d H:i'),
            ]);

        // Transaction chart data (last 30 days). Column is camelCase, so it must
        // be quoted for Postgres.
        $chartData = Transaction::select(
            DB::raw('DATE("createdAt") as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(price) as total')
        )
            ->where('createdAt', '>=', now()->subDays(30))
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
