<?php

namespace App\Services;

use App\Models\NinDetail;
use App\Models\Transaction;
use App\Models\User;
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
    public function verifyNin(string $userId, array $data): array
    {
        $reference = Transaction::generateReference('NIN');
        $amount = (float) config('services.verification.nin_price', 100);
        $verificationType = $data['verification_type'];

        $user = User::find($userId);
        if (! $user) {
            return ['success' => false, 'message' => 'User not found'];
        }

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
                $identityNumber = $data['last_name'].'_'.$data['first_name'];
                $apiPayload['last_name'] = $data['last_name'];
                $apiPayload['first_name'] = $data['first_name'];
                $apiPayload['gender'] = $data['gender'];
                $apiPayload['date_of_birth'] = $data['date_of_birth'];
                break;
        }

        $oldBalance = (float) $user->balance;

        if (! $user->debit($amount, false, ['fundingtype' => 'nin_verification'])) {
            return ['success' => false, 'message' => 'Insufficient wallet balance'];
        }

        $transaction = Transaction::create([
            'id' => $reference,
            'network' => 'NIN',
            'name' => 'NIN Verification ('.$verificationType.')',
            'price' => (int) round($amount),
            'type' => 'nin_verification',
            'phone' => $identityNumber,
            'oldbal' => $oldBalance,
            'newbal' => (float) $user->balance,
            'status' => 'pending',
            'userId' => $userId,
            'response' => 'pending',
        ]);

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer '.$this->ninApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->ninBaseUrl.'/verify-nin', $apiPayload);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? false)) {
                $verificationData = $body['data'] ?? [];

                $transaction->markAsSuccess($body['reference'] ?? null, json_encode($verificationData));

                NinDetail::create([
                    'id' => $reference,
                    'surname' => $verificationData['last_name'] ?? ($data['last_name'] ?? null),
                    'othernames' => $verificationData['first_name'] ?? ($data['first_name'] ?? null),
                    'idtype' => $verificationType,
                    'idvalue' => $identityNumber,
                    'sliptype' => 'verification',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'price' => (int) round($amount),
                    'status' => 'success',
                    'channel' => 'system',
                    'userId' => $userId,
                ]);

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
            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            $transaction->markAsFailed($body['message'] ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $body['message'] ?? 'NIN verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('NIN verification failed', [
                'reference' => $reference,
                'verification_type' => $verificationType,
                'error' => $e->getMessage(),
            ]);

            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
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
    public function verifyBvn(string $userId, string $bvnNumber): array
    {
        $reference = Transaction::generateReference('BVN');
        $amount = (float) config('services.verification.bvn_price', 150);

        if (! preg_match('/^\d{11}$/', $bvnNumber)) {
            return [
                'success' => false,
                'message' => 'Invalid BVN format. BVN must be 11 digits.',
            ];
        }

        $user = User::find($userId);
        if (! $user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $oldBalance = (float) $user->balance;

        if (! $user->debit($amount, false, ['fundingtype' => 'bvn_verification'])) {
            return ['success' => false, 'message' => 'Insufficient wallet balance'];
        }

        $transaction = Transaction::create([
            'id' => $reference,
            'network' => 'BVN',
            'name' => 'BVN Verification',
            'price' => (int) round($amount),
            'type' => 'bvn_verification',
            'phone' => $bvnNumber,
            'oldbal' => $oldBalance,
            'newbal' => (float) $user->balance,
            'status' => 'pending',
            'userId' => $userId,
            'response' => 'pending',
        ]);

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer '.$this->bvnApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->bvnBaseUrl.'/verify-bvn', [
                'bvn' => $bvnNumber,
                'reference' => $reference,
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? false)) {
                $verificationData = $body['data'] ?? [];

                $transaction->markAsSuccess($body['reference'] ?? null, json_encode($verificationData));

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
            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            $transaction->markAsFailed($body['message'] ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $body['message'] ?? 'BVN verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('BVN verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            $transaction->markAsFailed('Service temporarily unavailable');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Get user's verification history (from the transactions ledger).
     */
    public function getVerificationHistory(string $userId, ?string $type = null): array
    {
        $typeMap = ['nin' => 'nin_verification', 'bvn' => 'bvn_verification'];

        $query = Transaction::where('userId', $userId)
            ->whereIn('type', array_values($typeMap))
            ->orderByDesc('createdAt');

        if ($type && isset($typeMap[$type])) {
            $query->where('type', $typeMap[$type]);
        }

        return $query->get()->map(function (Transaction $txn) {
            $kind = $txn->type === 'bvn_verification' ? 'BVN' : 'NIN';

            return [
                'id' => $txn->id,
                'type' => $kind,
                'identity_number' => substr((string) $txn->phone, 0, 6).'****',
                'status' => $txn->status,
                'date' => $txn->createdAt?->format('Y-m-d H:i:s'),
                'amount' => (float) $txn->price,
            ];
        })->toArray();
    }
}
