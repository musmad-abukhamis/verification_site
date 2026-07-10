<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyDataRequest;
use App\Models\DataTransaction;
use App\Services\DataPurchaseService;

/**
 * API-role data purchases. Authenticated by the `api.token` middleware
 * (users.apitoken + role=API). Wraps the same DataPurchaseService as the web UI.
 */
class DataController extends Controller
{
    public function store(BuyDataRequest $request, DataPurchaseService $service)
    {
        try {
            $txn = $service->initiate($request->user(), $request->validated());
        } catch (InsufficientBalanceException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase accepted and is being processed.',
            'data' => $this->present($txn),
        ], 201);
    }

    public function show(string $reference)
    {
        $txn = DataTransaction::where('user_id', request()->user()->getKey())->find($reference);

        if (! $txn) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $this->present($txn)]);
    }

    private function present(DataTransaction $txn): array
    {
        return [
            'reference' => $txn->getKey(),
            'status' => $txn->status,
            'network' => $txn->network,
            'plan' => $txn->plan_name,
            'phone' => $txn->phone,
            'amount' => (float) $txn->price,
            'vendor_reference' => $txn->vendor_reference,
            'created_at' => $txn->created_at?->toIso8601String(),
        ];
    }
}
