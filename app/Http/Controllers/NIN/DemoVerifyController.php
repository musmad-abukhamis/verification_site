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
 * NIN Demo Verification Controller
 * Verifies by name, gender, DOB — always billed at premium price
 * Supports v1 (ArewaSmart demo API)
 */
class DemoVerifyController extends Controller
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

    /**
     * Show the Demo Verification page
     */
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('NIN/DemoVerify/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->getSlipPrice('premium'),
            'transactions' => Validation::where('userId', $user->id)
                ->where('comment', 'like', '%demo%')
                ->orderByDesc('createdAt')
                ->paginate(10)
                ->through(fn ($r) => $this->presentNinRecord($r)),
        ]);
    }

    /**
     * Demo verify — Provider 1 (ArewaSmart)
     */
    public function verifyV1(Request $request)
    {
        return $this->processVerification($request, 'v1');
    }

    /**
     * Demo verify — Provider 2 (ArewaSmart alt)
     */
    public function verifyV2(Request $request)
    {
        return $this->processVerification($request, 'v2');
    }

    protected function processVerification(Request $request, string $version)
    {
        $validated = $request->validate([
            'firstName' => 'required|string|min:2|max:100',
            'lastName' => 'required|string|min:2|max:100',
            'gender' => 'required|string|in:M,F,male,female,Male,Female',
            'dateOfBirth' => ['required', 'string', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'ref' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $price = $this->getSlipPrice('premium');

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_demo']);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.nin.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(config('services.nin.base_url').'/api/v1/nin/demo', [
                    'firstName' => $validated['firstName'],
                    'lastName' => $validated['lastName'],
                    'gender' => $validated['gender'],
                    'dateOfBirth' => $validated['dateOfBirth'],
                    'ref' => $validated['ref'] ?? null,
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && ! isset($body['error'])) {
                Validation::create([
                    'nin' => $body['nin'] ?? '',
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => "Demo verify {$version}: {$validated['firstName']} {$validated['lastName']}",
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                return back()->with([
                    'success' => 'NIN verified via demo successfully.',
                    'verification_data' => $body,
                ]);
            }

            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Demo verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            Validation::create([
                'nin' => '',
                'status' => 'failed',
                'result' => $resultPayload,
                'comment' => "[demo][HTTP {$httpStatus}] {$errorMessage}",
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error("NIN Demo Verify {$version} error: ".$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
