<?php

namespace App\Http\Controllers\Api\Reseller\Concerns;

use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Shared behaviour for the reseller endpoints whose work is finished by our
 * staff rather than by a provider: BVN modification, retrieval and SDK
 * onboarding.
 *
 * They differ from the verification endpoints in one way that shapes all of
 * them. There is no upstream call to succeed or fail, so nothing can come back
 * "unconfirmed": the request is either charged and on file, or refused and not
 * charged. That leaves only two failure modes worth distinguishing — the
 * caller's input and the caller's wallet — and no case where retrying could
 * file the same job twice by accident.
 */
trait FulfilledService
{
    /**
     * Take the fee, or explain why we cannot. Returns the price on success and
     * a ready-to-return refusal otherwise, so a caller reads:
     *
     *     [$price, $refusal] = $this->charge($user, 'bvn.retrieve.id', 'bvn_retrieval');
     *     if ($refusal) { return $refusal; }
     *
     * @return array{0: float|null, 1: JsonResponse|null}
     */
    protected function charge(User $user, string $service, string $fundingType): array
    {
        $price = ServicePrice::priceForUser($service, $user);

        // Null is never a price: it means no admin has set one or the service
        // was switched off. Guessing a default would bill an amount that
        // appears nowhere in the admin.
        if ($price === null) {
            return [null, $this->error('service_unavailable', 'This service is currently unavailable. Please contact support.', 503)];
        }

        // debit() is atomic and returns false when the balance moved under us,
        // so the explicit check above it would be a race on its own.
        if (! $user->debit($price, false, ['fundingtype' => $fundingType])) {
            return [null, $this->error('insufficient_balance', 'Insufficient wallet balance. Please fund your wallet.', 402)];
        }

        return [$price, null];
    }

    /**
     * Give the fee back. Used when persisting the request fails after we have
     * already taken payment for it.
     */
    protected function refund(User $user, float $price): void
    {
        $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
    }

    /**
     * The caller's own submissions, newest first.
     *
     * `$query` must already be scoped to the caller — this only orders, limits
     * and presents. Scoping is the calling controller's job because getting it
     * wrong here would leak every reseller's requests to every other one.
     */
    protected function listing($query, callable $present, int $limit): JsonResponse
    {
        $records = $query
            ->orderByDesc('createdAt')
            ->limit(min(max($limit, 1), 200))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ['submissions' => $records->map($present)->all()],
        ]);
    }

    /**
     * Wrap a newly created request in the accepted-submission envelope.
     *
     * `reference` repeats the record id rather than carrying an id of its own:
     * these tables have no reference column, and the docs promise a
     * `reference` on every response for support to quote.
     */
    protected function accepted(Model $record, float $price, array $data): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'reference' => $record->getKey(),
            'amount' => $price,
            'data' => $data,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ], $extra), $status);
    }
}
