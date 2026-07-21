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
            'message' => $this->humanMessage($txn),
            'data' => $this->present($txn),
        ] + $this->compat($txn), 201);
    }

    public function show(string $reference)
    {
        $txn = DataTransaction::where('user_id', request()->user()->getKey())->find($reference);

        if (! $txn) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->humanMessage($txn),
            'data' => $this->present($txn),
        ] + $this->compat($txn));
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

    /**
     * The flat fields the common data APIs return, so an integrator switching
     * to us does not have to rewrite their response parsing either.
     *
     * Merged alongside `status`/`message`/`data` rather than replacing them --
     * existing integrations keep working unchanged.
     *
     * `status` deliberately stays at the envelope level meaning "request
     * accepted"; the delivery state is `data.status` and `transaction_status`,
     * because our purchases complete asynchronously and a caller that reads a
     * top-level "success" as "delivered" would ship the wrong thing.
     */
    private function compat(DataTransaction $txn): array
    {
        return [
            'request-id' => $txn->client_ref,
            'transaction_status' => $txn->status,
            'network' => strtoupper($txn->network),
            'amount' => (string) $txn->price,
            'dataplan' => $txn->plan_name,
            'plan_type' => $txn->type,
            'phone_number' => $txn->phone,
            'oldbal' => (string) $txn->oldbal,
            'newbal' => (float) $txn->newbal,
            'system' => 'API',
            'wallet_vending' => 'wallet',
            'response' => $this->humanMessage($txn),
        ];
    }

    private function humanMessage(DataTransaction $txn): string
    {
        return match ($txn->status) {
            'success' => "You have gifted {$txn->plan_name} to {$txn->phone}.",
            'fail' => "Delivery of {$txn->plan_name} to {$txn->phone} failed and you were refunded.",
            'refunded', 'refunded_unconfirmed' => "Delivery of {$txn->plan_name} to {$txn->phone} was refunded.",
            default => "{$txn->plan_name} to {$txn->phone} is being processed.",
        };
    }
}
