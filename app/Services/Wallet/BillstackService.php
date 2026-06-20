<?php

namespace App\Services\Wallet;

use App\Models\AccountKyc;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reserved virtual-account funding via Billstack.
 *
 * Port of nimcweb's lib/virtualAccount/create-virtual-Account.ts. Creates a
 * PALMPAY virtual account for the user, attempts a BVN KYC upgrade, then
 * upserts the result into `accountkyc`. Incoming payments to the account are
 * credited to the wallet by the Billstack webhook (BillstackWebhookController).
 */
class BillstackService
{
    /**
     * Billstack -> accountkyc column for the chosen bank.
     */
    private const BANK_COLUMN = [
        '9PSB' => 'Ninesp',
        'SAFEHAVEN' => 'safehaven',
        'PROVIDUS' => 'providus',
        'BANKLY' => 'bankly',
        'PALMPAY' => 'palmpay',
    ];

    /**
     * Create a virtual account for the user and persist it to accountkyc.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function createAccountWithKYC(User $user, string $firstName, string $lastName, string $bvn, string $bank = 'PALMPAY'): array
    {
        $token = config('services.billstack.token');

        if (! $token) {
            return ['success' => false, 'message' => 'Service configuration error. Please contact support.'];
        }

        if (! $user->email) {
            return ['success' => false, 'message' => 'A valid email is required on your account.'];
        }

        $base = rtrim((string) config('services.billstack.base_url'), '/');
        $reference = 'REF_'.$user->id.'_'.now()->valueOf();

        try {
            // Step 1: create the virtual account.
            $accountResponse = Http::withToken($token)
                ->acceptJson()
                ->post("{$base}/thirdparty/generateVirtualAccount/", [
                    'reference' => $reference,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'bank' => $bank,
                ]);

            if (! $accountResponse->successful()) {
                Log::error('Billstack account creation failed', [
                    'status' => $accountResponse->status(),
                    'body' => $accountResponse->body(),
                ]);

                return ['success' => false, 'message' => 'Unable to create virtual account. Please check your details and try again.'];
            }

            $accountData = $accountResponse->json();

            if (! ($accountData['status'] ?? false) || empty($accountData['data']['account'][0])) {
                return ['success' => false, 'message' => $accountData['message'] ?? 'Invalid response from the funding provider.'];
            }

            $accountRef = $accountData['data']['reference'];
            $accountInfo = $accountData['data']['account'][0];
            $accountNumber = $accountInfo['account_number'];
            $accountName = $accountInfo['account_name'];

            // Step 2: upgrade KYC with the BVN (best-effort — keep the account
            // even if this leg fails).
            $kycStatus = 'generated';
            $kycMessage = 'Account created successfully.';

            $kycResponse = Http::withToken($token)
                ->acceptJson()
                ->post("{$base}/thirdparty/upgradeVirtualAccount/", [
                    'customer' => $user->email,
                    'bvn' => $bvn,
                ]);

            if ($kycResponse->successful() && ($kycResponse->json('status') ?? false)) {
                $kycStatus = 'kyc_completed';
                $kycMessage = 'Account created and KYC completed successfully.';
            } elseif (! $kycResponse->successful()) {
                Log::warning('Billstack KYC upgrade failed', [
                    'status' => $kycResponse->status(),
                    'body' => $kycResponse->body(),
                ]);
            }

            // Step 3: persist to accountkyc (one row per user).
            $column = self::BANK_COLUMN[$bank] ?? 'palmpay';

            AccountKyc::updateOrCreate(
                ['userId' => $user->id],
                [
                    'id' => $user->id,
                    'billstack_id' => $accountRef,
                    'firstname' => $firstName,
                    'surname' => $lastName,
                    'bvn' => $bvn,
                    'status' => $kycStatus,
                    'name' => $accountName,
                    $column => $accountNumber,
                ]
            );

            return [
                'success' => true,
                'message' => $kycMessage,
                'data' => [
                    'accountNumber' => $accountNumber,
                    'accountName' => $accountName,
                    'bankName' => $accountInfo['bank_name'] ?? null,
                    'bankId' => $accountInfo['bank_id'] ?? null,
                    'reference' => $accountRef,
                    'kycStatus' => $kycStatus,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Billstack account creation exception', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'An unexpected error occurred. Please try again later.'];
        }
    }
}
