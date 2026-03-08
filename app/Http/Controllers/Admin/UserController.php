<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with('wallet')
            ->latest();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif ($status === 'inactive') {
                $query->whereNull('email_verified_at');
            }
        }

        $users = $query->paginate(20)->through(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin' => $user->is_admin,
            'email_verified' => !is_null($user->email_verified_at),
            'wallet_balance' => $user->wallet?->total_balance ?? 0,
            'created_at' => $user->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(User $user)
    {
        $user->load(['wallet', 'transactions' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_admin' => $user->is_admin,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i'),
                'created_at' => $user->created_at->format('Y-m-d H:i'),
                'wallet' => $user->wallet ? [
                    'balance' => $user->wallet->balance,
                    'bonus_balance' => $user->wallet->bonus_balance,
                    'total_balance' => $user->wallet->total_balance,
                ] : null,
                'transactions' => $user->transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'status' => $t->status,
                    'created_at' => $t->created_at->format('Y-m-d H:i'),
                ]),
            ],
        ]);
    }

    public function toggleAdmin(User $user)
    {
        $user->update(['is_admin' => !$user->is_admin]);

        return back()->with('success', 'User admin status updated successfully.');
    }

    public function toggleStatus(User $user)
    {
        // Toggle email verification status as a simple way to disable/enable account
        if ($user->email_verified_at) {
            $user->update(['email_verified_at' => null]);
        } else {
            $user->update(['email_verified_at' => now()]);
        }

        return back()->with('success', 'User status updated successfully.');
    }

    public function creditWallet(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $oldBalance = $wallet->total_balance;
        $wallet->balance += $validated['amount'];
        $wallet->save();

        // Create transaction record
        \App\Models\Transaction::create([
            'user_id' => $user->id,
            'reference' => 'ADM_CREDIT_' . now()->timestamp . rand(1000, 9999),
            'type' => 'admin_credit',
            'status' => 'success',
            'amount' => $validated['amount'],
            'fee' => 0,
            'total_amount' => $validated['amount'],
            'details' => [
                'description' => $validated['description'] ?? 'Admin wallet credit',
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->total_balance,
            ],
            'provider' => 'admin',
        ]);

        return back()->with('success', '₦' . number_format($validated['amount']) . ' credited to ' . $user->name . '\'s wallet.');
    }

    public function debitWallet(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        if ($wallet->total_balance < $validated['amount']) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance. Current balance: ₦' . number_format($wallet->total_balance)]);
        }

        $oldBalance = $wallet->total_balance;

        // Deduct from bonus first, then balance
        if ($wallet->bonus_balance >= $validated['amount']) {
            $wallet->bonus_balance -= $validated['amount'];
        } else {
            $remaining = $validated['amount'] - $wallet->bonus_balance;
            $wallet->bonus_balance = 0;
            $wallet->balance -= $remaining;
        }
        $wallet->save();

        // Create transaction record
        \App\Models\Transaction::create([
            'user_id' => $user->id,
            'reference' => 'ADM_DEBIT_' . now()->timestamp . rand(1000, 9999),
            'type' => 'admin_debit',
            'status' => 'success',
            'amount' => $validated['amount'],
            'fee' => 0,
            'total_amount' => $validated['amount'],
            'details' => [
                'description' => $validated['description'] ?? 'Admin wallet debit',
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->total_balance,
            ],
            'provider' => 'admin',
        ]);

        return back()->with('success', '₦' . number_format($validated['amount']) . ' debited from ' . $user->name . '\'s wallet.');
    }
}
