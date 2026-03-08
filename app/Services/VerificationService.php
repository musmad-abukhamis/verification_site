<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\VerificationLog;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    protected string $ninBaseUrl;
    protected string $ninApiKey;
    protected string $bvnBaseUrl;
    protected string $bvnApiKey;

    public function __construct()
    {
        $this->ninBaseUrl = config('services.nin.base_url');
        $this->ninApiKey = config('services.nin.api_key');
        $this->bvnBaseUrl = config('services.bvn.base_url');
        $this->bvnApiKey = config('services.bvn.api_key');
    }

    /**
     * Verify NIN (National Identity Number)
     */
    public function verifyNin(int $userId, array $data): array
    {
        $reference = Transaction::generateReference();
        $amount = config('services.verification.nin_price', 100);
        $verificationType = $data['verification_type'];

        // Check wallet balance
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        if (!$wallet->debit($amount)) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ];
        }

        // Prepare API payload based on verification type
        $apiPayload = ['reference' => $reference];
        $identityNumber = '';

        switch ($verificationType) {
            case 'nin':
                $identityNumber = $data['nin_number'];
                $apiPayload['nin'] = $data['nin_number'];
                break;
            case 'phone':
                $identityNumber = $data['phone_number'];
                $apiPayload['phone_number'] = $data['phone_number'];
                break;
            case 'demographic':
                $identityNumber = $data['last_name'] . '_' . $data['first_name'];
                $apiPayload['last_name'] = $data['last_name'];
                $apiPayload['first_name'] = $data['first_name'];
                $apiPayload['gender'] = $data['gender'];
                $apiPayload['date_of_birth'] = $data['date_of_birth'];
                break;
        }

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $userId,
            'reference' => $reference,
            'type' => 'nin_verification',
            'status' => 'pending',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => [
                'verification_type' => $verificationType,
                'identity_number' => $identityNumber,
            ],
            'provider' => config('services.nin.provider', 'nimc'),
        ]);

        // Create verification log
        $verificationLog = VerificationLog::create([
            'user_id' => $userId,
            'transaction_id' => $transaction->id,
            'type' => 'nin',
            'identity_number' => $identityNumber,
            'status' => 'pending',
        ]);

        try {
            // Call NIN verification API
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->ninApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->ninBaseUrl . '/verify-nin', $apiPayload);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? false)) {
                $verificationData = $data['data'] ?? [];

                $verificationLog->update([
                    'verification_data' => $verificationData,
                    'status' => 'verified',
                ]);

                $transaction->markAsSuccess(
                    $data['reference'] ?? null,
                    'NIN verification successful'
                );

                return [
                    'success' => true,
                    'message' => 'NIN verification successful',
                    'data' => [
                        'reference' => $reference,
                        'verification_type' => $verificationType,
                        'identity_number' => $identityNumber,
                        'full_name' => $verificationData['full_name'] ?? null,
                        'date_of_birth' => $verificationData['date_of_birth'] ?? null,
                        'gender' => $verificationData['gender'] ?? null,
                        'phone' => $verificationData['phone'] ?? null,
                        'address' => $verificationData['address'] ?? null,
                    ],
                ];
            }

            // Failed - refund and update
            $wallet->credit($amount);
            $verificationLog->update([
                'status' => 'failed',
                'error_message' => $data['message'] ?? 'Verification failed',
            ]);
            $transaction->markAsFailed($data['message'] ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $data['message'] ?? 'NIN verification failed',
            ];

        } catch (\Exception $e) {
            Log::error('NIN verification failed', [
                'reference' => $reference,
                'verification_type' => $verificationType,
                'identity_number' => $identityNumber,
                'error' => $e->getMessage(),
            ]);

            $wallet->credit($amount);
            $verificationLog->update([
                'status' => 'failed',
                'error_message' => 'Service error',
            ]);
            $transaction->markAsFailed('Service temporarily unavailable');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Verify BVN (Bank Verification Number)
     */
    public function verifyBvn(int $userId, string $bvnNumber): array
    {
        $reference = Transaction::generateReference();
        $amount = config('services.verification.bvn_price', 150);

        // Validate BVN format (11 digits)
        if (!preg_match('/^\d{11}$/', $bvnNumber)) {
            return [
                'success' => false,
                'message' => 'Invalid BVN format. BVN must be 11 digits.',
            ];
        }

        // Check wallet balance
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        if (!$wallet->debit($amount)) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ];
        }

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $userId,
            'reference' => $reference,
            'type' => 'bvn_verification',
            'status' => 'pending',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => [
                'bvn_number' => $bvnNumber,
            ],
            'provider' => config('services.bvn.provider', 'nibss'),
        ]);

        // Create verification log
        $verificationLog = VerificationLog::create([
            'user_id' => $userId,
            'transaction_id' => $transaction->id,
            'type' => 'bvn',
            'identity_number' => $bvnNumber,
            'status' => 'pending',
        ]);

        try {
            // Call BVN verification API
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->bvnApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->bvnBaseUrl . '/verify-bvn', [
                'bvn' => $bvnNumber,
                'reference' => $reference,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? false)) {
                $verificationData = $data['data'] ?? [];

                $verificationLog->update([
                    'verification_data' => $verificationData,
                    'status' => 'verified',
                ]);

                $transaction->markAsSuccess(
                    $data['reference'] ?? null,
                    'BVN verification successful'
                );

                return [
                    'success' => true,
                    'message' => 'BVN verification successful',
                    'data' => [
                        'reference' => $reference,
                        'bvn' => $bvnNumber,
                        'full_name' => $verificationData['full_name'] ?? null,
                        'date_of_birth' => $verificationData['date_of_birth'] ?? null,
                        'gender' => $verificationData['gender'] ?? null,
                        'phone' => $verificationData['phone'] ?? null,
                        'email' => $verificationData['email'] ?? null,
                        'address' => $verificationData['address'] ?? null,
                        'nationality' => $verificationData['nationality'] ?? null,
                    ],
                ];
            }

            // Failed - refund and update
            $wallet->credit($amount);
            $verificationLog->update([
                'status' => 'failed',
                'error_message' => $data['message'] ?? 'Verification failed',
            ]);
            $transaction->markAsFailed($data['message'] ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $data['message'] ?? 'BVN verification failed',
            ];

        } catch (\Exception $e) {
            Log::error('BVN verification failed', [
                'reference' => $reference,
                'bvn' => $bvnNumber,
                'error' => $e->getMessage(),
            ]);

            $wallet->credit($amount);
            $verificationLog->update([
                'status' => 'failed',
                'error_message' => 'Service error',
            ]);
            $transaction->markAsFailed('Service temporarily unavailable');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Get user's verification history
     */
    public function getVerificationHistory(int $userId, ?string $type = null): array
    {
        $query = VerificationLog::where('user_id', $userId)
            ->with('transaction')
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'type' => strtoupper($log->type),
                'identity_number' => substr($log->identity_number, 0, 6) . '****',
                'status' => $log->status,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'amount' => $log->transaction->amount ?? 0,
            ];
        })->toArray();
    }
}
