<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VtuService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secretKey;

    public function __construct()
    {
        // Configure for your VTU provider (e.g., Vtpass, Clubkonnect)
        $this->baseUrl = config('services.vtu.base_url');
        $this->apiKey = config('services.vtu.api_key');
        $this->secretKey = config('services.vtu.secret_key');
    }

    /**
     * Purchase airtime
     */
    public function purchaseAirtime(
        int $userId,
        string $network,
        string $phoneNumber,
        float $amount,
        ?string $reference = null
    ): array {
        $reference = $reference ?? Transaction::generateReference();

        // Validate wallet balance
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

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $userId,
            'reference' => $reference,
            'type' => 'airtime',
            'status' => 'pending',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => [
                'network' => $network,
                'phone_number' => $phoneNumber,
                'amount' => $amount,
            ],
            'provider' => config('services.vtu.provider'),
        ]);

        try {
            // Call VTU API (example using Vtpass format)
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/pay', [
                'request_id' => $reference,
                'serviceID' => $this->getServiceId($network, 'airtime'),
                'amount' => $amount,
                'phone' => $phoneNumber,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['code'] ?? '') === '000') {
                $transaction->markAsSuccess(
                    $data['content']['transactions']['transactionId'] ?? null,
                    $data['response_description'] ?? 'Airtime purchase successful'
                );

                return [
                    'success' => true,
                    'message' => 'Airtime purchase successful',
                    'data' => [
                        'reference' => $reference,
                        'amount' => $amount,
                        'phone_number' => $phoneNumber,
                    ],
                ];
            }

            // Failed - refund wallet
            $wallet->credit($amount);
            $transaction->markAsFailed($data['response_description'] ?? 'Transaction failed');

            return [
                'success' => false,
                'message' => $data['response_description'] ?? 'Transaction failed',
            ];

        } catch (\Exception $e) {
            Log::error('Airtime purchase failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            // Refund on exception
            $wallet->credit($amount);
            $transaction->markAsFailed('Service temporarily unavailable');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Purchase data bundle
     */
    public function purchaseData(
        int $userId,
        string $network,
        string $phoneNumber,
        string $planCode,
        float $amount,
        ?string $reference = null
    ): array {
        $reference = $reference ?? Transaction::generateReference();

        // Validate wallet balance
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

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $userId,
            'reference' => $reference,
            'type' => 'data',
            'status' => 'pending',
            'amount' => $amount,
            'fee' => 0,
            'total_amount' => $amount,
            'details' => [
                'network' => $network,
                'phone_number' => $phoneNumber,
                'plan_code' => $planCode,
                'amount' => $amount,
            ],
            'provider' => config('services.vtu.provider'),
        ]);

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/pay', [
                'request_id' => $reference,
                'serviceID' => $this->getServiceId($network, 'data'),
                'billersCode' => $phoneNumber,
                'variation_code' => $planCode,
                'phone' => $phoneNumber,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['code'] ?? '') === '000') {
                $transaction->markAsSuccess(
                    $data['content']['transactions']['transactionId'] ?? null,
                    $data['response_description'] ?? 'Data purchase successful'
                );

                return [
                    'success' => true,
                    'message' => 'Data purchase successful',
                    'data' => [
                        'reference' => $reference,
                        'plan_code' => $planCode,
                        'phone_number' => $phoneNumber,
                    ],
                ];
            }

            // Failed - refund wallet
            $wallet->credit($amount);
            $transaction->markAsFailed($data['response_description'] ?? 'Transaction failed');

            return [
                'success' => false,
                'message' => $data['response_description'] ?? 'Transaction failed',
            ];

        } catch (\Exception $e) {
            Log::error('Data purchase failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            $wallet->credit($amount);
            $transaction->markAsFailed('Service temporarily unavailable');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    /**
     * Get available data plans from provider
     */
    public function getDataPlans(string $network): array
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey),
            ])->get($this->baseUrl . '/service-variations', [
                'serviceID' => $this->getServiceId($network, 'data'),
            ]);

            if ($response->successful()) {
                return $response->json()['content']['varations'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch data plans', [
                'network' => $network,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Verify phone number (check network)
     */
    public function verifyPhoneNumber(string $phoneNumber): array
    {
        $networks = [
            'mtn' => ['/^(0703|0706|0803|0806|0810|0813|0814|0816|0903|0906|0913|0916|07025|07026|0704)/'],
            'glo' => ['/^(0705|0805|0807|0811|0815|0905|0915)/'],
            'airtel' => ['/^(0701|0708|0802|0808|0812|0901|0902|0904|0907|0912|0911)/'],
            '9mobile' => ['/^(0809|0817|0818|0908|0909)/'],
        ];

        $phone = preg_replace('/^\+?234/', '0', $phoneNumber);

        foreach ($networks as $network => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $phone)) {
                    return [
                        'valid' => true,
                        'network' => $network,
                        'phone_number' => $phone,
                    ];
                }
            }
        }

        return [
            'valid' => false,
            'network' => null,
            'phone_number' => $phone,
        ];
    }

    /**
     * Map network to service ID
     */
    protected function getServiceId(string $network, string $service): string
    {
        $mapping = [
            'mtn' => ['airtime' => 'mtn', 'data' => 'mtn-data'],
            'glo' => ['airtime' => 'glo', 'data' => 'glo-data'],
            'airtel' => ['airtime' => 'airtel', 'data' => 'airtel-data'],
            '9mobile' => ['airtime' => 'etisalat', 'data' => 'etisalat-data'],
        ];

        return $mapping[strtolower($network)][$service] ?? $network;
    }
}
