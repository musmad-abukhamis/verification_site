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
    public function __construct(private BvnSearchService $search)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bvn' => ['required', 'string', 'regex:/^\d{11}$/'],
            'slip_type' => ['required', 'string', 'in:'.implode(',', array_keys(BvnSearchService::SLIP_SERVICES))],
        ]);

        $result = $this->search->search($request->user(), $validated['bvn'], $validated['slip_type']);

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
