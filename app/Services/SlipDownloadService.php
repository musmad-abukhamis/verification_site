<?php

namespace App\Services;

use App\Models\ServicePrice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Support\Facades\Auth;
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

        // Priced for this user's role, not the logged-in one -- they are the
        // same today, but the caller supplies the user being charged.
        $price = $this->getSlipPrice($slipTypeCode, $user);

        if ($price === null) {
            return [
                'success' => false,
                'message' => 'This slip type is currently unavailable.',
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
     * Map a slip-type code to its service key.
     */
    protected function slipService(string $slipType): string
    {
        return 'slip.'.match (strtolower($slipType)) {
            'regular', 'reg', 'regslip' => 'regular',
            'premium' => 'premium',
            'nvs' => 'nvs',
            'advanced', 'adv' => 'advanced',
            default => 'standard',
        };
    }

    /**
     * What this user pays for a slip type, or null when it is unavailable.
     */
    public function getSlipPrice(string $slipTypeCode, ?User $user = null): ?float
    {
        return ServicePrice::priceForUser($this->slipService($slipTypeCode), $user ?? Auth::user());
    }

    /**
     * Slip types the user can buy, at the price they would pay.
     */
    public function getActiveSlipTypes(?User $user = null): array
    {
        $types = [
            ['code' => 'regular', 'name' => 'Regular Slip', 'component_name' => 'RegularSlip'],
            ['code' => 'standard', 'name' => 'Standard Slip', 'component_name' => 'StandardSlip'],
            ['code' => 'premium', 'name' => 'Premium Slip', 'component_name' => 'PremiumSlip'],
            ['code' => 'nvs', 'name' => 'NVS Slip', 'component_name' => 'NvsSlip'],
            ['code' => 'advanced', 'name' => 'Advanced Slip', 'component_name' => 'AdvancedSlip'],
        ];

        $user ??= Auth::user();

        return array_values(array_filter(array_map(function (array $type) use ($user) {
            $price = $this->getSlipPrice($type['code'], $user);

            return $price !== null ? [...$type, 'price' => $price] : null;
        }, $types)));
    }
}
