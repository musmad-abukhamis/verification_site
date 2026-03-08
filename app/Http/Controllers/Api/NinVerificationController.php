<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NinVerificationRequest;
use App\Http\Requests\Api\NinDemoVerificationRequest;
use App\Http\Requests\Api\NinPhoneVerificationRequest;
use App\Http\Requests\Api\NinIpeSubmissionRequest;
use App\Models\NinValidation;
use App\Models\NinIpeClearance;
use App\Models\Wallet;
use App\Services\NinVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NinVerificationController extends Controller
{
    protected $verificationService;

    public function __construct(NinVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * NIN Verify - Provider 1 (Prembly)
     */
    public function verifyProvider1(NinVerificationRequest $request)
    {
        return $this->processVerification($request, 'provider1');
    }

    /**
     * NIN Verify - Provider 2 (ArewaSmart)
     */
    public function verifyProvider2(NinVerificationRequest $request)
    {
        return $this->processVerification($request, 'provider2');
    }

    /**
     * NIN Demo Verification
     */
    public function verifyDemo(NinDemoVerificationRequest $request)
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 400);
        }

        // Get premium price for demo verification
        $price = $this->verificationService->getPrice('premium');

        if ($wallet->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = $wallet->balance;
            $wallet->debit($price);

            $result = $this->verificationService->verifyDemo($request->validated());

            if ($result['success']) {
                $validation = NinValidation::create([
                    'user_id' => $user->id,
                    'nin' => $result['data']['nin'] ?? null,
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Demo verification successful',
                    'old_balance' => $oldBalance,
                    'new_balance' => $wallet->balance,
                    'reference' => 'Verify_' . now()->timestamp . rand(1000, 9999),
                    'validated_at' => now(),
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            } else {
                // Refund on failure
                $wallet->credit($price);
                DB::commit();

                return response()->json([
                    'status' => 'failed',
                    'error' => 'NIN Verification Failed',
                    'message' => $result['message'] ?? 'Verification failed'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Network error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * NIN Phone Verification
     */
    public function verifyPhone(NinPhoneVerificationRequest $request)
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 400);
        }

        // Get premium price for phone verification
        $price = $this->verificationService->getPrice('premium');

        if ($wallet->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = $wallet->balance;
            $wallet->debit($price);

            $result = $this->verificationService->verifyPhone($request->validated());

            if ($result['success']) {
                $validation = NinValidation::create([
                    'user_id' => $user->id,
                    'nin' => $result['data']['nin'] ?? null,
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Phone verification successful',
                    'old_balance' => $oldBalance,
                    'new_balance' => $wallet->balance,
                    'reference' => 'Verify_' . now()->timestamp . rand(1000, 9999),
                    'validated_at' => now(),
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            } else {
                // Refund on failure
                $wallet->credit($price);
                DB::commit();

                return response()->json([
                    'status' => 'failed',
                    'error' => 'NIN Verification Failed',
                    'message' => $result['message'] ?? 'Verification failed'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Network error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * IPE Submission - Provider 1 (Nguru)
     */
    public function submitIpeProvider1(NinIpeSubmissionRequest $request)
    {
        return $this->processIpeSubmission($request, 'provider1');
    }

    /**
     * IPE Submission - Provider 2 (ArewaSmart)
     */
    public function submitIpeProvider2(NinIpeSubmissionRequest $request)
    {
        return $this->processIpeSubmission($request, 'provider2');
    }

    /**
     * Get All IPE Submissions
     */
    public function getAllIpeSubmissions()
    {
        $submissions = NinIpeClearance::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $submissions], 200);
    }

    /**
     * Check IPE Status (ArewaSmart)
     */
    public function checkIpeStatus(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required|string|size:15'
        ]);

        $result = $this->verificationService->checkIpeStatus($request->tracking_id);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json(['error' => $result['message'] ?? 'Status check failed'], 400);
    }

    /**
     * Process NIN verification for both providers
     */
    protected function processVerification(NinVerificationRequest $request, string $provider)
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 400);
        }

        // Get price based on slip type
        $price = $this->verificationService->getPrice($request->slipType);

        if ($wallet->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = $wallet->balance;
            $wallet->debit($price);

            $result = $this->verificationService->verifyNin(
                $request->validated(),
                $provider
            );

            if ($result['success']) {
                $validation = NinValidation::create([
                    'user_id' => $user->id,
                    'nin' => $result['data']['nin'] ?? $request->idValue,
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Verification successful',
                    'old_balance' => $oldBalance,
                    'new_balance' => $wallet->balance,
                    'reference' => 'Verify_' . now()->timestamp . rand(1000, 9999),
                    'validated_at' => now(),
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            } else {
                // Refund on failure
                $wallet->credit($price);
                DB::commit();

                return response()->json([
                    'status' => 'failed',
                    'reference' => $result['reference'] ?? null,
                    'error' => 'NIN Verification Failed',
                    'message' => $result['message'] ?? 'Verification failed'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Network error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process IPE submission for both providers
     */
    protected function processIpeSubmission(NinIpeSubmissionRequest $request, string $provider)
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 400);
        }

        // Get IPE price
        $price = $this->verificationService->getIpePrice();

        if ($wallet->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = $wallet->balance;
            $wallet->debit($price);

            $trackingId = $provider === 'provider1' 
                ? $request->trkid 
                : $request->tracking_id;

            $result = $this->verificationService->submitIpe(
                $trackingId,
                $provider,
                $request->description ?? 'My Reference'
            );

            if ($result['success']) {
                $ipeClearance = NinIpeClearance::create([
                    'user_id' => $user->id,
                    'nin' => $trackingId,
                    'status' => 'processing',
                    'result' => 'Pending',
                    'comment' => $provider === 'provider1' ? 'New submission' : 'Submitted to ArewaSmart',
                    'old_balance' => $oldBalance,
                    'new_balance' => $wallet->balance,
                    'reference' => 'IPE_' . now()->timestamp . rand(1000, 9999),
                    'cleared_at' => null,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $ipeClearance->id,
                        'trkid' => $trackingId,
                        'result' => 'Pending',
                        'status' => 'processing',
                        'comment' => $ipeClearance->comment,
                        'user_id' => $user->id,
                        'oldBal' => $oldBalance,
                        'newBal' => $wallet->balance,
                        'created_at' => $ipeClearance->created_at,
                        'updated_at' => $ipeClearance->updated_at,
                    ]
                ], 201);
            } else {
                // Refund on failure
                $wallet->credit($price);
                DB::commit();

                return response()->json([
                    'error' => $result['message'] ?? 'IPE submission failed'
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Network error: ' . $e->getMessage()], 500);
        }
    }
}