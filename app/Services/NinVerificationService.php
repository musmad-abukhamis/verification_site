<?php

namespace App\Services;

use App\Models\ServicePrice;
use App\Models\User;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NIN verification for the Sanctum API.
 *
 * Provider selection comes from the routing chain in Admin > Verification —
 * the old provider1/provider2 arguments are gone, along with the two versioned
 * upstream endpoints they mapped to.
 */
class NinVerificationService
{
    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

    /**
     * Verify a NIN or phone number through the routed chain.
     */
    public function verifyNin(array $data): array
    {
        $idType = $data['idType'] ?? 'nin';
        $service = $idType === 'phone' ? 'nin.phone' : 'nin.verify';
        $field = $idType === 'phone' ? 'phone' : 'nin';

        try {
            $outcome = $this->dispatcher->verify(
                $service,
                [$field => $data['idValue']],
                ['user_id' => Auth::id()],
            );

            if ($outcome->isSuccess()) {
                return [
                    'success' => true,
                    'data' => $outcome->data + ['provider' => $outcome->providerName],
                ];
            }

            return [
                'success' => false,
                'message' => $outcome->message ?? 'Verification failed',
                'reference' => 'Verify_'.now()->timestamp,
            ];
        } catch (\Exception $e) {
            Log::error('NIN Verification Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Network error occurred',
                'reference' => 'Verify_'.now()->timestamp,
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
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/nin/demo', [
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
            Log::error('NIN Demo Verification Error: '.$e->getMessage());

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
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/nin/phone', [
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
            Log::error('NIN Phone Verification Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * Submit IPE request
     */
    public function submitIpe(string $trackingId, string $description = 'My Reference'): array
    {
        try {
            $outcome = $this->dispatcher->verify(
                'nin.ipe',
                ['tracking_id' => $trackingId],
                ['user_id' => Auth::id()],
            );

            if ($outcome->isSuccess()) {
                return [
                    'success' => true,
                    'data' => $outcome->data,
                    'provider' => $outcome->providerName,
                ];
            }

            return [
                'success' => false,
                'message' => $outcome->message ?? 'IPE submission failed',
            ];
        } catch (\Exception $e) {
            Log::error('IPE Submission Error: '.$e->getMessage());

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
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl.'/nin/ipe/arewa/status', [
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
            Log::error('IPE Status Check Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Network error occurred',
            ];
        }
    }

    /**
     * NIN verification price for a user. Both provider versions share one fee.
     *
     * These used to read config('services.nin.prices.*'), which meant the API
     * resellers were billed from a config file while the web UI billed from the
     * database -- two prices for the same service. Everything is now
     * service_prices, which Admin > Service Prices edits, and it is role-aware:
     * an API reseller can be charged a different rate from a retail user.
     */
    public function getVerificationPrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.verify', $user ?? Auth::user());
    }

    /**
     * Verification by phone number.
     */
    public function getPhoneVerifyPrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.phone', $user ?? Auth::user());
    }

    /**
     * Verification by demographic details.
     */
    public function getDemoVerifyPrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.demographic', $user ?? Auth::user());
    }

    /**
     * Get IPE submission price
     */
    public function getIpePrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.ipe', $user ?? Auth::user());
    }
}
