<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()
            ->with('user')
            ->latest();

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by reference
        if ($search = $request->input('search')) {
            $query->where('reference', 'like', "%{$search}%");
        }

        $transactions = $query->paginate(20)->through(fn ($t) => [
            'id' => $t->id,
            'reference' => $t->reference,
            'user' => $t->user?->name ?? 'Unknown',
            'type' => $t->type,
            'amount' => $t->amount,
            'fee' => $t->fee,
            'total_amount' => $t->total_amount,
            'status' => $t->status,
            'provider' => $t->provider,
            'created_at' => $t->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'status', 'search']),
            'types' => Transaction::distinct()->pluck('type'),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('user');

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'user' => $transaction->user ? [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->name,
                    'email' => $transaction->user->email,
                ] : null,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'fee' => $transaction->fee,
                'total_amount' => $transaction->total_amount,
                'status' => $transaction->status,
                'details' => $transaction->details,
                'provider' => $transaction->provider,
                'provider_reference' => $transaction->provider_reference,
                'response_message' => $transaction->response_message,
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $transaction->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,success,failed',
        ]);

        $transaction->update(['status' => $validated['status']]);

        return back()->with('success', 'Transaction status updated successfully.');
    }
}
