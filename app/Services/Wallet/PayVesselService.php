<?php

namespace App\Services\Wallet;

use App\Models\AccountKyc;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * STATIC virtual-account funding via PayVessel.
 *
 * Port of nimcweb's actions/payvesselReserved/route.ts, replacing Billstack as
 * the funding provider. One request returns an account per requested bank; the
 * numbers are stored on the user's accountkyc row and money paid into them is
 * credited by PayVesselWebhookController.
 */
class PayVesselService
{
    /**
     * PayVessel bankName => [accountkyc number column, name column].
     *
     * Matched case-insensitively: the API returns "PalmPay", while nimcweb
     * switched on "Palmpay" and so silently dropped every PalmPay account.
     *
     * PalmPay lands in palmpay2 (not palmpay) because Billstack used palmpay --
     * keeping them apart means an existing Billstack account keeps working.
     */
    private const BANK_COLUMNS = [
        'palmpay' => ['palmpay2', 'palmpay2_name'],
        '9payment service bank' => ['Ninesp', 'ninesp_name'],
        'rubies mfb' => ['moniepoint', 'name'],
    ];

    /**
     * Create the user's static virtual accounts and persist them.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function createAccounts(User $user, string $bvn, ?string $nin = null): array
    {
        $key = config('services.payvessel.key');
        $secret = config('services.payvessel.secret');
        $businessId = config('services.payvessel.business_id');

        if (! $key || ! $secret || ! $businessId) {
            Log::error('PayVessel is not configured');

            return ['success' => false, 'message' => 'Service configuration error. Please contact support.'];
        }

        if (! $user->email) {
            return ['success' => false, 'message' => 'A valid email is required on your account.'];
        }

        $payload = [
            'email' => $user->email,
            'name' => $user->name ?: $user->username,
            'phoneNumber' => (string) $user->phone,
            'bankcode' => config('services.payvessel.bank_codes'),
            'account_type' => 'STATIC',
            'businessid' => $businessId,
            'bvn' => $bvn,
        ];

        if ($nin) {
            $payload['nin'] = $nin;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['api-key' => $key, 'api-secret' => $secret])
                ->acceptJson()
                ->post(
                    rtrim((string) config('services.payvessel.base_url'), '/')
                    .'/pms/api/external/request/customerReservedAccount/',
                    $payload
                );
        } catch (\Throwable $e) {
            Log::error('PayVessel request failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Could not reach the funding provider. Please try again later.'];
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('PayVessel rejected account creation', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                // PayVessel's message is usually specific ("invalid bvn"), so
                // it is more useful to the user than a generic string.
                'message' => $response->json('message') ?: 'Unable to create your virtual account. Please check your BVN and try again.',
            ];
        }

        $banks = $response->json('banks') ?: [];

        if (! $banks) {
            Log::error('PayVessel returned no accounts', ['body' => $response->body()]);

            return ['success' => false, 'message' => 'The funding provider returned no accounts. Please contact support.'];
        }

        return $this->persist($user, $banks, $bvn, $nin);
    }

    /**
     * @param  array<int, array<string, mixed>>  $banks
     * @return array{success: bool, message: string, data?: array}
     */
    private function persist(User $user, array $banks, string $bvn, ?string $nin): array
    {
        $columns = [];
        $accounts = [];
        $unmapped = [];

        foreach ($banks as $bank) {
            $bankName = (string) ($bank['bankName'] ?? '');
            $number = $bank['accountNumber'] ?? null;
            $accountName = $bank['accountName'] ?? null;

            if (! $number) {
                continue;
            }

            $map = self::BANK_COLUMNS[mb_strtolower($bankName)] ?? null;

            if (! $map) {
                // Do not fail the request: the user still gets the accounts we
                // do understand. But this needs to be visible, because an
                // unmapped bank means money could arrive at an account we
                // cannot attribute to anyone.
                $unmapped[] = $bankName;

                continue;
            }

            [$numberColumn, $nameColumn] = $map;

            $columns[$numberColumn] = $number;
            $columns[$nameColumn] = $accountName;

            $accounts[] = [
                'bank' => $bankName,
                'account_number' => $number,
                'account_name' => $accountName,
            ];
        }

        if ($unmapped) {
            Log::error('PayVessel returned banks with no accountkyc column', [
                'banks' => $unmapped,
                'userId' => $user->id,
            ]);
        }

        if (! $accounts) {
            return ['success' => false, 'message' => 'The funding provider returned no usable accounts. Please contact support.'];
        }

        $tracking = $banks[0]['trackingReference'] ?? null;

        AccountKyc::updateOrCreate(
            ['userId' => $user->id],
            array_filter([
                'id' => $user->id,
                'payvessel_id' => $tracking,
                'bvn' => $bvn,
                'nin' => $nin,
                'status' => 'generated',
                'firstname' => $user->name ?: $user->username,
            ] + $columns, fn ($value) => $value !== null)
        );

        return [
            'success' => true,
            'message' => 'Your funding accounts have been created.',
            'data' => ['accounts' => $accounts],
        ];
    }
}
