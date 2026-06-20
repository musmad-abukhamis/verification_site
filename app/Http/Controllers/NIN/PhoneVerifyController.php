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
 * NIN Phone Verification Controller
 * Supports v1 (ArewaSmart phone API) — always billed at premium price
 */
class PhoneVerifyController extends Controller
{
    use NinWalletTrait;

    /**
     * Show the Phone Verification page
     */
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('NIN/PhoneVerify/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->getSlipPrice('premium'),
            'transactions' => Validation::where('userId', $user->id)
                ->where('comment', 'like', '%phone%')
                ->orderByDesc('createdAt')
                ->paginate(10)
                ->through(fn ($r) => $this->presentNinRecord($r)),
        ]);
    }

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /**
     * Verify NIN via phone — Provider 1 (ArewaSmart)
     */
    public function verifyV1(Request $request)
    {
        return $this->processVerification($request, 'v1');
    }

    /**
     * Verify NIN via phone — Provider 2 (ArewaSmart alt)
     */
    public function verifyV2(Request $request)
    {
        return $this->processVerification($request, 'v2');
    }

    protected function processVerification(Request $request, string $version)
    {
        $validated = $request->validate([
            'idType' => 'required|string|max:255',
            'idValue' => 'required|string|min:10|max:15|regex:/^0[0-9]{10}$/',
            'value' => 'nullable|string|min:10|max:15|regex:/^0[0-9]{10}$/',
            'ref' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $price = $this->getSlipPrice('premium');

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_phone']);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.nin.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(config('services.nin.base_url').'/api/v1/nin/phone', [
                    'value' => $validated['value'] ?? null,
                    'idType' => $validated['idType'],
                    'idValue' => $validated['idValue'],
                    'ref' => $validated['ref'] ?? null,
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && ! isset($body['error'])) {
                Validation::create([
                    'nin' => $body['nin'] ?? $validated['idValue'],
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => "Phone verify {$version}: {$validated['idValue']}",
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                return back()->with([
                    'success' => 'NIN retrieved via phone successfully.',
                    'verification_data' => $body,
                ]);
            }

            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Phone verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            Validation::create([
                'nin' => $validated['idValue'],
                'status' => 'failed',
                'result' => $resultPayload,
                'comment' => "[phone][HTTP {$httpStatus}] {$errorMessage}",
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error("NIN Phone Verify {$version} error: ".$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
