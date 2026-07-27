<?php

namespace App\Services;

use App\Models\NinDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * NIN/BVN verification for the /verification/* pages.
 *
 * The provider is no longer a pair of hardcoded config endpoints: both methods
 * go through VerificationDispatcher, which walks the chain configured in
 * Admin > Verification in priority order, fails over when one declines, and
 * hands back a normalized record whatever shape the provider replied in.
 *
 * The wallet flow is unchanged — debit up front, refund on any non-success.
 */
class VerificationService
{
    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

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

        // Canonical inputs — the engine's field maps rename them per provider.
        $identityNumber = '';
        $service = 'nin.verify';
        $input = [];

        switch ($verificationType) {
            case 'nin':
                $identityNumber = $data['nin_number'];
                $input = ['nin' => $data['nin_number']];
                break;
            case 'phone':
                $identityNumber = $data['phone_number'];
                $service = 'nin.phone';
                $input = ['phone' => $data['phone_number']];
                break;
            case 'demographic':
                $identityNumber = $data['last_name'].'_'.$data['first_name'];
                $service = 'nin.demographic';
                $input = [
                    'last_name' => $data['last_name'],
                    'first_name' => $data['first_name'],
                    'gender' => $data['gender'],
                    'date_of_birth' => $data['date_of_birth'],
                ];
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
            $outcome = $this->dispatcher->verify($service, $input, [
                'user_id' => $userId,
                'reference' => $reference,
            ]);

            if ($outcome->isSuccess()) {
                $verificationData = $outcome->data;

                $transaction->markAsSuccess($outcome->reference, json_encode($verificationData));

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
                        'address' => $verificationData['residence_address'] ?? null,
                        'provider' => $outcome->providerName,
                    ],
                ];
            }

            // Every routed provider declined (or none answered) — refund.
            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            $transaction->markAsFailed($outcome->message ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $outcome->message ?? 'NIN verification failed',
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
            $outcome = $this->dispatcher->verify('bvn.verify', ['bvn' => $bvnNumber], [
                'user_id' => $userId,
                'reference' => $reference,
            ]);

            if ($outcome->isSuccess()) {
                $verificationData = $outcome->data;

                $transaction->markAsSuccess($outcome->reference, json_encode($verificationData));

                return [
                    'success' => true,
                    'message' => 'BVN verification successful',
                    'data' => [
                        'reference' => $reference,
                        'bvn' => $verificationData['bvn'] ?? $bvnNumber,
                        'full_name' => $verificationData['full_name'] ?? null,
                        'date_of_birth' => $verificationData['date_of_birth'] ?? null,
                        'gender' => $verificationData['gender'] ?? null,
                        'phone' => $verificationData['phone'] ?? null,
                        'email' => $verificationData['email'] ?? null,
                        'address' => $verificationData['residence_address'] ?? null,
                        'nationality' => $verificationData['nationality'] ?? null,
                        'photo' => $verificationData['photo'] ?? null,
                        'provider' => $outcome->providerName,
                    ],
                ];
            }

            // Every routed provider declined (or none answered) — refund.
            $user->credit($amount, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            $transaction->markAsFailed($outcome->message ?? 'Verification failed');

            return [
                'success' => false,
                'message' => $outcome->message ?? 'BVN verification failed',
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
