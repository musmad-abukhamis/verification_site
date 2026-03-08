<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NinVerificationService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.nin.base_url', 'https://api.example.com');
        $this->apiKey = config('services.nin.api_key', '');
    }

    /**
     * Verify NIN using specified provider
     */
    public function verifyNin(array $data, string $provider): array
    {
        try {
            $endpoint = $provider === 'provider1' ? '/nin/verify_1' : '/nin/verify_2';
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . $endpoint, [
                'idType' => $data['idType'],
                'idValue' => $data['idValue'],
                'slipType' => $data['slipType'],
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Verification failed',
                'reference' => 'Verify_' . now()->timestamp,
            ];
        } catch (\Exception $e) {
            Log::error('NIN Verification Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error occurred',
                'reference' => 'Verify_' . now()->timestamp,
            ];
        }
    }

    /**
     * Verify NIN using demo method (name, gender, DOB)
     */
    public function verifyDemo(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/nin/demo', [
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'gender' => $data['gender'],
                'dateOfBirth' => $data['dateOfBirth'],
                'ref' => $data['ref'] ?? null,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Demo verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('NIN Demo Verification Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * Verify NIN using phone number
     */
    public function verifyPhone(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/nin/phone', [
                'value' => $data['value'],
                'ref' => $data['ref'] ?? null,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Phone verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('NIN Phone Verification Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * Submit IPE request
     */
    public function submitIpe(string $trackingId, string $provider, string $description = 'My Reference'): array
    {
        try {
            if ($provider === 'provider1') {
                $endpoint = '/nin/ipe';
                $payload = ['trkid' => $trackingId];
            } else {
                $endpoint = '/nin/ipe2';
                $payload = [
                    'tracking_id' => $trackingId,
                    'description' => $description,
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . $endpoint, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'IPE submission failed',
            ];
        } catch (\Exception $e) {
            Log::error('IPE Submission Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * Check IPE status
     */
    public function checkIpeStatus(string $trackingId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/nin/ipe/arewa/status', [
                'tracking_id' => $trackingId,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Status check failed',
            ];
        } catch (\Exception $e) {
            Log::error('IPE Status Check Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * Get verification price based on slip type
     */
    public function getPrice(string $slipType): float
    {
        $prices = [
            'premium' => config('services.nin.prices.premium', 150),
            'standard' => config('services.nin.prices.standard', 100),
            'regular' => config('services.nin.prices.regular', 50),
        ];

        return $prices[$slipType] ?? $prices['regular'];
    }

    /**
     * Get IPE submission price
     */
    public function getIpePrice(): float
    {
        return config('services.nin.prices.ipe', 50);
    }
}