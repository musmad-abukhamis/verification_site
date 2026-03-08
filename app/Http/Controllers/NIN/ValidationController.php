<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\NinValidation;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Validation Controller
 * Tracks/validates a NIN (submit NIN number & log it)
 * Supports v1 (Prembly) and v2 (ArewaSmart)
 */
class ValidationController extends Controller
{
    use NinWalletTrait;

    /**
     * Show the NIN Validation page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $query = NinValidation::where('user_id', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sortField = in_array($request->input('sort'), ['id', 'nin', 'status', 'created_at', 'updated_at'])
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $transactions = $query
            ->orderBy($sortField, $request->input('direction', 'desc'))
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('NIN/Validation/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => (float) $wallet->total_balance,
            ],
            'price' => (float) config('services.nin.prices.standard', 100),
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
        ]);
    }

    /**
     * Validate NIN — Provider 1 (Prembly)
     */
    public function storeV1(Request $request)
    {
        return $this->processValidation($request, 'v1');
    }

    /**
     * Validate NIN — Provider 2 (ArewaSmart)
     */
    public function storeV2(Request $request)
    {
        return $this->processValidation($request, 'v2');
    }

    /**
     * Check status of a pending NIN validation
     */
    public function checkStatus(Request $request, NinValidation $validation)
    {
        if ($validation->user_id !== Auth::id()) {
            abort(403);
        }

        if ($validation->status === 'processing') {
            $validation->update([
                'status'       => 'completed',
                'result'       => 'NIN validated successfully',
                'comment'      => 'Validation completed',
                'validated_at' => now(),
            ]);
        }

        return back()->with('success', 'Status updated successfully');
    }

    protected function processValidation(Request $request, string $version)
    {
        $validated = $request->validate([
            'nin' => 'required|string|size:11',
        ]);

        $user   = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        $price = (float) config('services.nin.prices.standard', 100);

        if ($wallet->total_balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = $wallet->total_balance;
        $this->debitWallet($wallet, $price);
        $reference = 'NIN_' . now()->timestamp . rand(1000, 9999);

        try {
            // Use verify_1 or verify_2 endpoint with idType=nin
            $endpoint = $version === 'v1'
                ? config('services.nin.base_url') . '/api/v1/nin/verify_1'
                : config('services.nin.base_url') . '/api/v1/nin/verify_2';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.nin.api_key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post($endpoint, [
                    'idType'   => 'nin',
                    'idValue'  => $validated['nin'],
                    'slipType' => 'standard',
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && !isset($body['error'])) {
                NinValidation::create([
                    'user_id'      => $user->id,
                    'nin'          => $validated['nin'],
                    'status'       => 'completed',
                    'result'       => json_encode($body),
                    'comment'      => "NIN validation {$version}",
                    'old_balance'  => $oldBalance,
                    'new_balance'  => $wallet->fresh()->total_balance,
                    'reference'    => $reference,
                    'validated_at' => now(),
                ]);

                return back()->with('success', "NIN validated successfully. Reference: {$reference}");
            }

            // Refund on failure
            $this->creditWallet($wallet, $price);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Validation failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status'  => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            NinValidation::create([
                'user_id'     => $user->id,
                'nin'         => $validated['nin'],
                'status'      => 'failed',
                'result'      => $resultPayload,
                'comment'     => "[HTTP {$httpStatus}] {$errorMessage}",
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->fresh()->total_balance,
                'reference'   => $reference,
            ]);

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            $this->creditWallet($wallet, $price);
            Log::error("NIN Validation {$version} error: " . $e->getMessage());
            return back()->withErrors(['message' => 'Network error: ' . $e->getMessage()]);
        }
    }
}
