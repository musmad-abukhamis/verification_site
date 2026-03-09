<?php

namespace App\Services;

use App\Models\NinValidation;
use App\Models\SlipType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlipDownloadService
{
    /**
     * Process slip download request
     *
     * @param int $validationId The NIN validation record ID
     * @param string $slipTypeCode The slip type code (e.g., 'standard', 'premium')
     * @param User $user The authenticated user
     * @return array Result with success status and data or error message
     */
    public function download(int $validationId, string $slipTypeCode, User $user): array
    {
        // Validate the verification exists and belongs to user
        $validation = $this->validateVerification($validationId, $user);
        if (!$validation) {
            return [
                'success' => false,
                'message' => 'Verification record not found or not valid.',
            ];
        }

        // Get the slip type
        $slipType = SlipType::findByCode($slipTypeCode);
        if (!$slipType) {
            return [
                'success' => false,
                'message' => 'Invalid slip type.',
            ];
        }

        // Get the slip price
        $price = (float) $slipType->price;

        // Get user's wallet
        $wallet = $user->wallet;
        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'Wallet not found.',
            ];
        }

        // Check balance
        if ($wallet->total_balance < $price) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance.',
                'required' => $price,
                'available' => $wallet->total_balance,
            ];
        }

        try {
            DB::beginTransaction();

            // Refresh wallet to get latest balance
            $wallet->refresh();
            $oldBalance = $wallet->total_balance;

            // Deduct from wallet
            $this->debitWallet($wallet, $price);

            // Create transaction record
            $transaction = Transaction::createSlipDownload(
                $user->id,
                $price,
                [
                    'validation_id' => $validationId,
                    'slip_type' => $slipTypeCode,
                    'slip_name' => $slipType->name,
                    'nin' => $validation->nin,
                    'old_balance' => $oldBalance,
                    'new_balance' => $wallet->fresh()->total_balance,
                ]
            );

            DB::commit();

            // Get the verification result data
            $resultData = $validation->getParsedResult();

            return [
                'success' => true,
                'message' => 'Slip download processed successfully.',
                'data' => [
                    'validation_id' => $validationId,
                    'slip_type' => $slipTypeCode,
                    'component_name' => $slipType->component_name,
                    'price' => $price,
                    'transaction_id' => $transaction->id,
                    'verification_data' => $resultData,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Slip download error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'validation_id' => $validationId,
                'slip_type' => $slipTypeCode,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that a verification record exists and belongs to the user
     */
    public function validateVerification(int $validationId, User $user): ?NinValidation
    {
        return NinValidation::where('id', $validationId)
            ->where('user_id', $user->id)
            ->where('is_verified', true)
            ->where('status', 'completed')
            ->first();
    }

    /**
     * Get the price for a slip type
     */
    public function getSlipPrice(string $slipTypeCode): float
    {
        return SlipType::getPrice($slipTypeCode, 100);
    }

    /**
     * Get all active slip types for frontend
     */
    public function getActiveSlipTypes(): array
    {
        return SlipType::getForFrontend();
    }

    /**
     * Debit wallet (bonus first, then main balance)
     */
    protected function debitWallet(Wallet $wallet, float $amount): void
    {
        if ($wallet->bonus_balance >= $amount) {
            // Deduct entirely from bonus balance
            $wallet->bonus_balance -= $amount;
        } else {
            // Use all bonus balance, then deduct remainder from main balance
            $remaining = $amount - $wallet->bonus_balance;
            $wallet->bonus_balance = 0;
            $wallet->balance -= $remaining;
        }
        $wallet->save();
    }
}
