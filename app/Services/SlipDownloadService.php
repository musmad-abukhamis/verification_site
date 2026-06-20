<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Validation;
use App\Models\VerifyApiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlipDownloadService
{
    /**
     * Process slip download request.
     *
     * @param  int  $validationId  The NIN validation record ID
     * @param  string  $slipTypeCode  The slip type code (e.g., 'standard', 'premium')
     * @param  User  $user  The authenticated user
     * @return array Result with success status and data or error message
     */
    public function download(int $validationId, string $slipTypeCode, User $user): array
    {
        // Validate the verification exists and belongs to user
        $validation = $this->validateVerification($validationId, $user);
        if (! $validation) {
            return [
                'success' => false,
                'message' => 'Verification record not found or not valid.',
            ];
        }

        $price = $this->getSlipPrice($slipTypeCode);
        if ($price <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid slip type.',
            ];
        }

        // Check balance
        if ((float) $user->balance < $price) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance.',
                'required' => $price,
                'available' => (float) $user->balance,
            ];
        }

        try {
            DB::beginTransaction();

            $oldBalance = (float) $user->balance;

            // Deduct from wallet
            $user->debit($price, false, ['fundingtype' => 'nin_slip']);

            // Create transaction record
            $transaction = Transaction::createSlipDownload(
                $user->id,
                $price,
                [
                    'validation_id' => $validationId,
                    'slip_type' => $slipTypeCode,
                    'slip_name' => ucfirst($slipTypeCode).' Slip',
                    'nin' => $validation->nin,
                    'old_balance' => $oldBalance,
                    'new_balance' => (float) $user->balance,
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Slip download processed successfully.',
                'data' => [
                    'validation_id' => $validationId,
                    'slip_type' => $slipTypeCode,
                    'component_name' => ucfirst($slipTypeCode).'Slip',
                    'price' => $price,
                    'transaction_id' => $transaction->id,
                    'verification_data' => $validation->getParsedResult(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Slip download error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'validation_id' => $validationId,
                'slip_type' => $slipTypeCode,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Validate that a verification record exists, belongs to the user and is completed.
     */
    public function validateVerification(int $validationId, User $user): ?Validation
    {
        return Validation::where('id', $validationId)
            ->where('userId', $user->id)
            ->where('status', 'completed')
            ->first();
    }

    /**
     * Single-row verifyapiconfiq pricing.
     */
    protected function verifyConfig(): VerifyApiConfig
    {
        return Cache::remember('verifyapiconfiq.API1', 300, function () {
            return VerifyApiConfig::firstOrCreate(['id' => 'API1']);
        });
    }

    protected function slipPriceColumn(string $slipType): string
    {
        return match (strtolower($slipType)) {
            'regular', 'reg', 'regslip' => 'regslipprice',
            'premium' => 'premiumslipprice',
            'nvs' => 'nvsslipprice',
            'advanced', 'adv' => 'advslipprice',
            default => 'standardslipsprice',
        };
    }

    /**
     * Get the price for a slip type.
     */
    public function getSlipPrice(string $slipTypeCode): float
    {
        return (float) ($this->verifyConfig()->{$this->slipPriceColumn($slipTypeCode)} ?? 100);
    }

    /**
     * Get all active slip types for frontend.
     */
    public function getActiveSlipTypes(): array
    {
        $types = [
            ['code' => 'regular', 'name' => 'Regular Slip', 'component_name' => 'RegularSlip'],
            ['code' => 'standard', 'name' => 'Standard Slip', 'component_name' => 'StandardSlip'],
            ['code' => 'premium', 'name' => 'Premium Slip', 'component_name' => 'PremiumSlip'],
            ['code' => 'nvs', 'name' => 'NVS Slip', 'component_name' => 'NvsSlip'],
            ['code' => 'advanced', 'name' => 'Advanced Slip', 'component_name' => 'AdvancedSlip'],
        ];

        return array_values(array_filter(array_map(function (array $type) {
            $price = (float) ($this->verifyConfig()->{$this->slipPriceColumn($type['code'])} ?? 0);

            return $price > 0 ? [...$type, 'price' => $price] : null;
        }, $types)));
    }
}
