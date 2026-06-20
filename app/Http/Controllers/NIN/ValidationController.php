<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Validation;
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

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'created_at', 'createdAt' => 'createdAt',
            'updated_at', 'updatedAt' => 'updatedAt',
            'id', 'nin', 'status' => $sort,
            default => 'createdAt',
        };
    }

    /**
     * Show the NIN Validation page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Validation::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transactions = $query
            ->orderBy($this->sortColumn($request->input('sort')), $request->input('direction', 'desc'))
            ->paginate(10)
            ->through(fn ($r) => $this->presentNinRecord($r))
            ->withQueryString();

        return Inertia::render('NIN/Validation/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->getValidationPrice(),
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
    public function checkStatus(Request $request, Validation $validation)
    {
        if ($validation->userId !== Auth::id()) {
            abort(403);
        }

        if ($validation->status === 'processing') {
            $validation->update([
                'status' => 'completed',
                'result' => 'NIN validated successfully',
                'comment' => 'Validation completed',
            ]);
        }

        return back()->with('success', 'Status updated successfully');
    }

    protected function processValidation(Request $request, string $version)
    {
        $validated = $request->validate([
            'nin' => 'required|string|size:11',
        ]);

        $user = Auth::user();
        $price = $this->getValidationPrice();

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_validation']);
        $reference = 'NIN_'.now()->timestamp.random_int(1000, 9999);

        try {
            // Use verify_1 or verify_2 endpoint with idType=nin
            $endpoint = $version === 'v1'
                ? config('services.nin.base_url').'/api/v1/nin/verify_1'
                : config('services.nin.base_url').'/api/v1/nin/verify_2';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.nin.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'idType' => 'nin',
                    'idValue' => $validated['nin'],
                    'slipType' => 'standard',
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && ! isset($body['error'])) {
                Validation::create([
                    'nin' => $validated['nin'],
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => "NIN validation {$version}",
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                return back()->with('success', "NIN validated successfully. Reference: {$reference}");
            }

            // Refund on failure
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Validation failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            Validation::create([
                'nin' => $validated['nin'],
                'status' => 'failed',
                'result' => $resultPayload,
                'comment' => "[HTTP {$httpStatus}] {$errorMessage}",
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error("NIN Validation {$version} error: ".$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
