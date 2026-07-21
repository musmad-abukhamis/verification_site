<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NinDemoVerificationRequest;
use App\Http\Requests\Api\NinIpeSubmissionRequest;
use App\Http\Requests\Api\NinPhoneVerificationRequest;
use App\Http\Requests\Api\NinVerificationRequest;
use App\Models\Ipe;
use App\Models\Validation;
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
     * A service no admin has priced yet. 503 rather than 500: nothing is broken,
     * the operator just has to set a price in Admin > Service Prices.
     */
    protected function unpricedService()
    {
        return response()->json(['error' => 'This service is not priced yet. Please contact support.'], 503);
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
        $price = $this->verificationService->getDemoVerifyPrice();

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = (float) $user->balance;
            $user->debit($price, false, ['fundingtype' => 'nin_demo']);

            $result = $this->verificationService->verifyDemo($request->validated());

            if ($result['success']) {
                Validation::create([
                    'nin' => $result['data']['nin'] ?? '',
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Demo verification successful',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            }

            // Refund on failure
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            DB::commit();

            return response()->json([
                'status' => 'failed',
                'error' => 'NIN Verification Failed',
                'message' => $result['message'] ?? 'Verification failed',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Network error: '.$e->getMessage()], 500);
        }
    }

    /**
     * NIN Phone Verification
     */
    public function verifyPhone(NinPhoneVerificationRequest $request)
    {
        $user = Auth::user();
        $price = $this->verificationService->getPhoneVerifyPrice();

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = (float) $user->balance;
            $user->debit($price, false, ['fundingtype' => 'nin_phone']);

            $result = $this->verificationService->verifyPhone($request->validated());

            if ($result['success']) {
                Validation::create([
                    'nin' => $result['data']['nin'] ?? '',
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Phone verification successful',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            }

            // Refund on failure
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            DB::commit();

            return response()->json([
                'status' => 'failed',
                'error' => 'NIN Verification Failed',
                'message' => $result['message'] ?? 'Verification failed',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Network error: '.$e->getMessage()], 500);
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
        $submissions = Ipe::with('user')
            ->orderByDesc('createdAt')
            ->get();

        return response()->json(['data' => $submissions], 200);
    }

    /**
     * Check IPE Status (ArewaSmart)
     */
    public function checkIpeStatus(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required|string|size:15',
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
        // One verification fee regardless of the slip type requested -- the slip
        // itself is charged separately, by SlipDownloadService.
        $price = $this->verificationService->getVerificationPrice();

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = (float) $user->balance;
            $user->debit($price, false, ['fundingtype' => 'nin_verification']);

            $result = $this->verificationService->verifyNin($request->validated(), $provider);

            if ($result['success']) {
                Validation::create([
                    'nin' => $result['data']['nin'] ?? $request->idValue,
                    'status' => 'completed',
                    'result' => json_encode($result['data']),
                    'comment' => 'Verification successful',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                DB::commit();

                return response()->json($result['data'], 200);
            }

            // Refund on failure
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            DB::commit();

            return response()->json([
                'status' => 'failed',
                'reference' => $result['reference'] ?? null,
                'error' => 'NIN Verification Failed',
                'message' => $result['message'] ?? 'Verification failed',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Network error: '.$e->getMessage()], 500);
        }
    }

    /**
     * Process IPE submission for both providers
     */
    protected function processIpeSubmission(NinIpeSubmissionRequest $request, string $provider)
    {
        $user = Auth::user();
        $price = $this->verificationService->getIpePrice();

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        try {
            DB::beginTransaction();

            $oldBalance = (float) $user->balance;
            $user->debit($price, false, ['fundingtype' => 'nin_ipe']);

            $trackingId = $provider === 'provider1'
                ? $request->trkid
                : $request->tracking_id;

            $result = $this->verificationService->submitIpe(
                $trackingId,
                $provider,
                $request->description ?? 'My Reference'
            );

            if ($result['success']) {
                $ipeClearance = Ipe::create([
                    'trkid' => $trackingId,
                    'status' => 'processing',
                    'result' => 'Pending',
                    'comment' => $provider === 'provider1' ? 'New submission' : 'Submitted to ArewaSmart',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
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
                        'newBal' => (float) $user->balance,
                        'created_at' => $ipeClearance->createdAt,
                        'updated_at' => $ipeClearance->updatedAt,
                    ],
                ], 201);
            }

            // Refund on failure
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            DB::commit();

            return response()->json([
                'error' => $result['message'] ?? 'IPE submission failed',
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Network error: '.$e->getMessage()], 500);
        }
    }
}
