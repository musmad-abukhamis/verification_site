<?php

namespace App\Http\Controllers;

use App\Models\DataTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DataTransactionController extends Controller
{
    public function index(Request $request)
    {
        $base = DataTransaction::where('user_id', Auth::id());

        $query = clone $base;

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%")
                    ->orWhere('plan_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (DataTransaction $t) => [
                'reference' => $t->id,
                'network' => $t->network,
                'plan_name' => $t->plan_name,
                'type' => $t->type,
                'price' => (float) $t->price,
                'phone' => $t->phone,
                'status' => $t->status,
                'date' => $t->created_at?->format('M d, Y H:i'),
            ])
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
