<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DataTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = DataTransaction::query()->with('user:id,name,username,email');

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('plan_name', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%");
            });
        }
        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        $transactions = $query->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (DataTransaction $t) => [
                'reference' => $t->id,
                'user' => $t->user?->name ?? $t->user?->username ?? 'Unknown',
                'network' => $t->network,
                'plan_name' => $t->plan_name,
                'phone' => $t->phone,
                'price' => (float) $t->price,
                'status' => $t->status,
                'attempts' => $t->attempts,
                'date' => $t->created_at?->format('M d, Y H:i'),
            ])
            ->withQueryString();

        return Inertia::render('Admin/DataTransactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status']),
            'exceptionsCount' => DataTransaction::where('status', 'refunded_unconfirmed')->count(),
        ]);
    }

    public function show(DataTransaction $dataTransaction)
    {
        $dataTransaction->load(['user:id,name,username,email', 'attemptLogs.vendor:id,name']);

        return Inertia::render('Admin/DataTransactions/Show', [
            'transaction' => [
                'reference' => $dataTransaction->id,
                'user' => $dataTransaction->user?->name ?? $dataTransaction->user?->username,
                'status' => $dataTransaction->status,
                'network' => $dataTransaction->network,
                'type' => $dataTransaction->type,
                'plan_name' => $dataTransaction->plan_name,
                'phone' => $dataTransaction->phone,
                'ported' => (bool) $dataTransaction->ported,
                'price' => (float) $dataTransaction->price,
                'oldbal' => (float) $dataTransaction->oldbal,
                'newbal' => (float) $dataTransaction->newbal,
                'vendor_reference' => $dataTransaction->vendor_reference,
                'raw_response' => $dataTransaction->raw_response,
                'date' => $dataTransaction->created_at?->format('M d, Y H:i:s'),
                'attempts' => $dataTransaction->attemptLogs->map(fn ($a) => [
                    'vendor' => $a->vendor?->name ?? $a->vendor_id,
                    'outcome' => $a->outcome,
                    'request' => $a->request_payload,
                    'response' => $a->response,
                    'at' => $a->created_at?->format('M d, Y H:i:s'),
                ]),
            ],
        ]);
    }
}
