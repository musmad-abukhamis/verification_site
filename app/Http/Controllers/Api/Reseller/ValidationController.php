<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NIN\NinWalletTrait;
use App\Models\Validation;
use App\Services\Nin\Providers\RoutedProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NIN validation for API resellers.
 *
 * Same routed chain as NIN verification, but its own service and its own price
 * (`nin.validation`): validation answers "is this NIN real and whose is it",
 * where verification is the full record an integrator renders a slip from.
 * Keeping them apart is what lets an operator price a cheap yes/no check
 * separately from a full lookup.
 *
 * Runs the web screen's flow, so the charge, the `validation` log row and the
 * refund-on-failure behaviour are identical.
 */
class ValidationController extends Controller
{
    use NinWalletTrait;

    public function __construct(private readonly RoutedProvider $routed) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nin' => ['required', 'string', 'regex:/^\d{11}$/'],
        ]);

        $user = $request->user();
        $price = $this->getValidationPrice($user);

        if ($price === null) {
            return $this->error('service_unavailable', 'This service is currently unavailable. Please contact support.', 503);
        }

        if ((float) $user->balance < $price) {
            return $this->error('insufficient_balance', 'Insufficient wallet balance. Please fund your wallet.', 402);
        }

        $reference = Validation::generateReference();
        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_validation']);

        $result = $this->routed->verifyByNin($validated['nin']);

        if ($result->success) {
            $data = $result->data ?? [];

            $record = Validation::create([
                'nin' => $validated['nin'],
                'status' => 'completed',
                'result' => json_encode($data),
                'comment' => "[{$reference}] NIN validation via ".($data['provider'] ?? 'routing'),
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'reference' => $reference,
                'amount' => $price,
                'valid' => true,
                'data' => array_merge($data, ['validation_id' => $record->id]),
            ]);
        }

        // A NIN the chain could not confirm is not a billable result.
        $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

        $message = $result->message ?? 'Validation failed.';

        Validation::create([
            'nin' => $validated['nin'],
            'status' => 'failed',
            'result' => json_encode($result->raw ?? ['message' => $message]),
            'comment' => "[{$reference}] ".$message,
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'userId' => $user->id,
        ]);

        return $this->error(
            $result->errorCode ?? 'verification_failed',
            $message,
            $result->httpStatus ?: 422,
            ['reference' => $reference, 'valid' => false, 'refunded' => true],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ], $extra), $status);
    }
}
