<?php

namespace App\Http\Controllers\Api\Nin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NIN\NinWalletTrait;
use App\Http\Requests\Nin\ProviderVerifyRequest;
use App\Models\User;
use App\Models\Validation;
use App\Services\Nin\Contracts\NinProvider;
use App\Services\Nin\NinProviderManager;
use App\Services\Nin\VerificationResult;
use App\Services\Verification\FailedVerificationChargeResult;
use App\Services\Verification\FailedVerificationChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Base controller for all per-provider NIN verification endpoints.
 *
 * Each concrete provider controller is a thin subclass that only declares
 * its provider key via providerKey(). This base owns the shared flow:
 *   validate -> resolve provider -> check wallet -> debit (per-provider price)
 *   -> call provider -> log Validation -> refund on failure
 *   -> return a consistent JSON envelope.
 */
abstract class AbstractProviderController extends Controller
{
    use NinWalletTrait;

    public function __construct(
        protected NinProviderManager $providers,
        protected FailedVerificationChargeService $failedCharges,
    ) {
    }

    /** The provider key this controller serves (e.g. "prembly"). */
    abstract protected function providerKey(): string;

    /**
     * Single verify endpoint; the method (nin|phone|demographic) is in the body.
     */
    public function verify(ProviderVerifyRequest $request): JsonResponse
    {
        $provider = $this->providers->get($this->providerKey());

        if (! $provider || ! $provider->isActive()) {
            return $this->error('provider_unavailable', 'This provider is not available.', 503);
        }

        $method = $request->input('method');

        if (! in_array($method, $provider->supportedMethods(), true)) {
            return $this->error('method_not_supported', "This provider does not support verification by {$method}.", 422);
        }

        $user = Auth::user();
        $price = $provider->priceFor($method, $user);

        if ($price === null) {
            return $this->error('service_unpriced', 'This service is currently unavailable. Please contact support.', 503);
        }

        if ((float) $user->balance < $price) {
            return $this->error('insufficient_balance', 'Insufficient wallet balance. Please fund your wallet.', 402);
        }

        $oldBalance = (float) $user->balance;
        $reference = Validation::generateReference();

        DB::beginTransaction();

        try {
            $this->debitWallet($user, $price, ['fundingtype' => 'nin_'.$method]);

            $result = $this->callProvider($provider, $method, $request);

            if ($result->success) {
                $validation = $this->logSuccess($user->id, $provider, $method, $request, $result, $oldBalance, $user, $price, $reference);
                DB::commit();

                return $this->success($provider, $method, $result, $validation, $reference);
            }

            // Provider returned a business failure -> refund the verification
            // price in full, then apply the admin-configured failed-verification
            // fee if (and only if) the provider actually answered "no record".
            // A timeout or a provider outage lands here too, and must not bill.
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $charge = $this->failedCharges->chargeForResult(
                $user,
                $this->serviceFor($method),
                $reference,
                $result,
                ['identity_value' => $this->idValueFor($method, $request)],
            );

            $validation = $this->logFailure($user->id, $provider, $method, $request, $result, $oldBalance, $user, $reference, $charge);
            $charge->record?->update(['record_id' => (string) $validation->id]);

            DB::commit();

            return $this->error(
                $result->errorCode ?? 'verification_failed',
                $charge->decorate($result->message ?: 'Verification failed.'),
                $result->httpStatus ?: 422,
                [
                    'provider' => $provider->key(),
                    'method' => $method,
                    'reference' => $reference,
                    'charge' => $charge->toArray(),
                ],
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            // Wallet changes rolled back with the transaction; nothing to refund.
            report($e);

            return $this->error('server_error', 'A server error occurred. You were not charged.', 500);
        }
    }

    protected function callProvider(NinProvider $provider, string $method, ProviderVerifyRequest $request): VerificationResult
    {
        return match ($method) {
            'nin' => $provider->verifyByNin($request->input('nin')),
            'phone' => $provider->verifyByPhone($request->input('phone')),
            'demographic' => $provider->verifyByDemographic($request->demographic()),
        };
    }

    protected function logSuccess(string $userId, NinProvider $provider, string $method, ProviderVerifyRequest $request, VerificationResult $result, float $oldBalance, User $user, float $price, string $reference): Validation
    {
        return Validation::create([
            'nin' => $result->data['nin'] ?? $request->input('nin') ?? $this->idValueFor($method, $request) ?? '',
            'status' => 'completed',
            'result' => json_encode($result->data),
            'comment' => "{$provider->label()} verify ({$method}) [{$this->idValueFor($method, $request)}]",
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'service' => $this->serviceFor($method),
            'userId' => $userId,
        ]);
    }

    /**
     * Written after the failed-verification fee is settled, so `newBal` is the
     * balance the user is actually left with.
     */
    protected function logFailure(string $userId, NinProvider $provider, string $method, ProviderVerifyRequest $request, VerificationResult $result, float $oldBalance, User $user, string $reference, ?FailedVerificationChargeResult $charge = null): Validation
    {
        $note = $charge?->charged
            ? ' (failed-verification fee ₦'.number_format($charge->amount, 2).' charged)'
            : '';

        return Validation::create([
            'nin' => $request->input('nin') ?? $this->idValueFor($method, $request) ?? '',
            'status' => 'failed',
            'result' => json_encode($result->raw ?? ['message' => $result->message]),
            'comment' => "[{$result->errorCode}] ".($result->message ?? 'Verification failed').$note,
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'service' => $this->serviceFor($method),
            'userId' => $userId,
        ]);
    }

    /**
     * The ServiceCatalog key behind a verification method — which is what
     * decides whether the NIN or the BVN failed-charge toggle applies.
     */
    protected function serviceFor(string $method): string
    {
        return match ($method) {
            'phone' => 'nin.phone',
            'demographic' => 'nin.demographic',
            default => 'nin.verify',
        };
    }

    protected function idValueFor(string $method, ProviderVerifyRequest $request): ?string
    {
        return match ($method) {
            'nin' => $request->input('nin'),
            'phone' => $request->input('phone'),
            'demographic' => trim($request->input('first_name').' '.$request->input('last_name')),
            default => null,
        };
    }

    protected function success(NinProvider $provider, string $method, VerificationResult $result, Validation $validation, string $reference): JsonResponse
    {
        return response()->json([
            'success' => true,
            'provider' => $provider->key(),
            'method' => $method,
            'reference' => $reference,
            'data' => array_merge($result->data ?? [], ['validation_id' => $validation->id]),
        ], 200);
    }

    protected function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => array_merge(['code' => $code, 'message' => $message], $extra),
        ], $status);
    }
}
