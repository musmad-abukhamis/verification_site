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
            ->latest('createdAt');

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by reference (id)
        if ($search = $request->input('search')) {
            $query->where('id', 'like', "%{$search}%");
        }

        $transactions = $query->paginate(20)->through(fn (Transaction $t) => [
            'id' => $t->id,
            'reference' => $t->reference,
            'user' => $t->user?->name ?? 'Unknown',
            'type' => $t->type,
            'amount' => (float) $t->price,
            'fee' => 0,
            'total_amount' => (float) $t->price,
            'status' => $t->status,
            'provider' => $t->network,
            'created_at' => $t->createdAt?->format('Y-m-d H:i'),
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
                'amount' => (float) $transaction->price,
                'fee' => 0,
                'total_amount' => (float) $transaction->price,
                'status' => $transaction->status,
                'details' => $transaction->details,
                'provider' => $transaction->network,
                'provider_reference' => null,
                'response_message' => $transaction->response,
                'created_at' => $transaction->createdAt?->format('Y-m-d H:i:s'),
                'updated_at' => $transaction->createdAt?->format('Y-m-d H:i:s'),
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
