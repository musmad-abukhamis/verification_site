<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Bvn\BvnSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BVN lookup for API resellers.
 *
 * Runs the same BvnSearchService as the web UI, so the price (role-aware), the
 * NINDetails log entry and the refund-on-failure behaviour are identical.
 */
class BvnController extends Controller
{
    /**
     * The slip an integrator gets when they do not ask for one. Premium is the
     * full slip -- the product listed simply as "BVN Slip" -- so omitting the
     * field gives the complete record rather than a partial one the caller then
     * has to pay a second time to complete.
     */
    private const DEFAULT_SLIP = 'premium';

    public function __construct(private BvnSearchService $search)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bvn' => ['required', 'string', 'regex:/^\d{11}$/'],
            'slip_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(BvnSearchService::SLIP_SERVICES))],
        ]);

        $slipType = $validated['slip_type'] ?? self::DEFAULT_SLIP;

        $result = $this->search->search($request->user(), $validated['bvn'], $slipType);

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'code' => $result['code'],
                'reference' => $result['reference'],
            ], $this->statusFor($result['code']));
        }

        return response()->json([
            'status' => 'success',
            'reference' => $result['reference'],
            // Echoed because it can be defaulted, and it decides the price.
            'slip_type' => $slipType,
            'amount' => $result['price'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Distinguish "your fault", "your wallet" and "our provider" so an
     * integrator can decide whether retrying is worth anything.
     */
    private function statusFor(string $code): int
    {
        return match ($code) {
            'insufficient_balance' => 402,
            'service_unavailable' => 503,
            'provider_error' => 502,
            default => 422,
        };
    }
}
