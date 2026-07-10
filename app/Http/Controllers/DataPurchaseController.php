<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\BuyDataRequest;
use App\Models\DataTransaction;
use App\Services\DataCache;
use App\Services\DataPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DataPurchaseController extends Controller
{
    /**
     * The four supported networks (canonical lowercase value + display label).
     */
    private const NETWORKS = [
        ['value' => 'mtn', 'label' => 'MTN'],
        ['value' => 'airtel', 'label' => 'AIRTEL'],
        ['value' => 'glo', 'label' => 'GLO'],
        ['value' => '9mobile', 'label' => '9MOBILE'],
    ];

    public function index(Request $request)
    {
        $user = Auth::user();

        return Inertia::render('BuyData/Index', [
            'networks' => self::NETWORKS,
            'plans' => DataCache::catalogForRole($user->role),
            'prefixMap' => DataCache::prefixMap(),
            'balance' => (float) $user->balance,
            'lastPurchase' => $this->lastPurchase($user->getKey()),
            'beneficiaries' => $user->beneficiaries()
                ->latest('updated_at')
                ->limit(10)
                ->get(['phone', 'network', 'is_ported', 'label']),
            // Live status prop — refreshed by router.reload({ only: ['transaction'] }).
            'transaction' => $this->transactionProp($request->query('ref'), $user->getKey()),
        ]);
    }

    public function store(BuyDataRequest $request, DataPurchaseService $service)
    {
        try {
            $txn = $service->initiate($request->user(), $request->validated());
        } catch (InsufficientBalanceException $e) {
            return back()->withErrors(['balance' => $e->getMessage()]);
        }

        return redirect()->route('buy-data', ['ref' => $txn->getKey()]);
    }

    /**
     * JSON status endpoint (used as a lightweight poll fallback).
     */
    public function status(string $reference)
    {
        $txn = DataTransaction::where('user_id', Auth::id())->findOrFail($reference);

        return response()->json($this->present($txn));
    }

    private function lastPurchase(string $userId): ?array
    {
        $txn = DataTransaction::where('user_id', $userId)
            ->where('status', 'success')
            ->latest('created_at')
            ->first();

        if (! $txn) {
            return null;
        }

        return [
            'plan_id' => $txn->plan_id,
            'plan_name' => $txn->plan_name,
            'network' => $txn->network,
            'phone' => $txn->phone,
            'price' => (float) $txn->price,
        ];
    }

    private function transactionProp(?string $reference, string $userId): ?array
    {
        if (! $reference) {
            return null;
        }

        $txn = DataTransaction::where('user_id', $userId)->find($reference);

        return $txn ? $this->present($txn) : null;
    }

    private function present(DataTransaction $txn): array
    {
        return [
            'reference' => $txn->getKey(),
            'status' => $txn->status,
            'plan_name' => $txn->plan_name,
            'network' => $txn->network,
            'phone' => $txn->phone,
            'price' => (float) $txn->price,
            'ported' => (bool) $txn->ported,
            'terminal' => $txn->isTerminal(),
            'message' => $this->statusMessage($txn),
        ];
    }

    private function statusMessage(DataTransaction $txn): string
    {
        return match ($txn->status) {
            'success' => 'Your data purchase was successful.',
            'refunded' => 'The purchase failed and your wallet was refunded.',
            'refunded_unconfirmed' => 'We could not confirm delivery, so your wallet was refunded. If the data arrives, no further charge applies.',
            'fail' => 'The purchase failed.',
            default => 'Processing your purchase…',
        };
    }
}
