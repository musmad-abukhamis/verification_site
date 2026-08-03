<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NIN\NinWalletTrait;
use App\Models\User;
use App\Models\Validation;
use App\Services\Nin\AsyncJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NIN validation for API resellers.
 *
 * Validation is an asynchronous job, not a lookup: the submission files the work
 * and the result lands 3 days to a week later. So this endpoint answers 202 with
 * a `processing` record rather than a verdict, and the integrator polls
 * GET /nin/validate/{submission} for the outcome — the same shape the IPE
 * endpoints already use.
 *
 * It shares the price (`nin.validation`), the `validation` log row and the
 * refund rules with the web screen, so a record submitted either way is settled
 * identically from the admin.
 *
 * NIN *verification* is the instant lookup an integrator renders a slip from,
 * and it lives at /nin/verify. This endpoint used to call that chain and return
 * a verdict immediately, which billed the validation price for a verification
 * result and left the caller no way to learn what the validation actually did.
 */
class ValidationController extends Controller
{
    use NinWalletTrait;

    private const SERVICE = 'nin.validation';

    public function __construct(private readonly AsyncJobService $jobs) {}

    /**
     * File a NIN validation.
     */
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

        try {
            $outcome = $this->jobs->submit(self::SERVICE, [
                'nin' => $validated['nin'],
                'description' => $reference,
            ], ['user_id' => $user->id, 'reference' => $reference]);
        } catch (\Throwable $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            report($e);

            return $this->error('provider_error', 'The submission could not be sent. You were not charged.', 502, [
                'reference' => $reference,
            ]);
        }

        if ($outcome->isSuccess()) {
            $record = $this->record(
                $user, $validated['nin'], 'processing', 'Pending',
                "[{$reference}] Submitted to ".($outcome->providerName ?? 'provider').' via API',
                $oldBalance, $price, $outcome->providerId, $outcome->reference,
            );

            return response()->json([
                'status' => 'processing',
                'reference' => $reference,
                'amount' => $price,
                'message' => 'Validation submitted. Results typically take 3 days to a week — poll this submission for the outcome.',
                'data' => $this->present($record),
            ], 202);
        }

        // Charge reversed either way. What differs is what the caller may do
        // next, so the two outcomes are never collapsed into one status.
        $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

        if ($outcome->isTimeout()) {
            // The submission may exist upstream, so it stays on file as
            // `processing` for reconciliation and must not be retried blindly.
            $record = $this->record(
                $user, $validated['nin'], 'processing', 'Unconfirmed',
                "[{$reference}] ".($outcome->message ?? 'No confirmation from provider'),
                $oldBalance, $price, $outcome->providerId, null, refunded: true,
            );

            return response()->json([
                'status' => 'unconfirmed',
                'reference' => $reference,
                'refunded' => true,
                'message' => 'The provider did not confirm this submission. It may still have been filed — do not resubmit. Poll this submission or contact support to reconcile it.',
                'data' => $this->present($record),
            ], 202);
        }

        $record = $this->record(
            $user, $validated['nin'], 'failed', 'Failed',
            "[{$reference}] ".($outcome->message ?? 'Submission failed'),
            $oldBalance, $price, $outcome->providerId, null, refunded: true,
        );

        return $this->error('submission_failed', $outcome->message ?? 'NIN validation submission failed.', 422, [
            'reference' => $reference,
            'refunded' => true,
            'data' => $this->present($record),
        ]);
    }

    /**
     * The caller's own submissions, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $submissions = Validation::where('userId', $request->user()->id)
            ->validations()
            ->orderByDesc('createdAt')
            ->limit(min((int) $request->input('limit', 50), 200))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ['submissions' => $submissions->map(fn (Validation $r) => $this->present($r))->all()],
        ]);
    }

    /**
     * One submission, refreshed from the provider if it is still open.
     *
     * Polling has to actually ask the provider — an integrator has no Check
     * button, and a record that only ever changes when a human opens the web
     * screen would sit at `processing` forever.
     */
    public function show(Request $request, string $submission): JsonResponse
    {
        $query = Validation::where('userId', $request->user()->id)->validations();

        $record = ctype_digit($submission)
            ? $query->find($submission)
            // The same NIN can be submitted more than once; the latest is the
            // one an integrator polling for a result cares about.
            : $query->where('nin', $submission)->orderByDesc('createdAt')->first();

        if (! $record) {
            return $this->error('not_found', 'No submission found for that id.', 404);
        }

        if (! $record->isTerminal()) {
            $this->jobs->refresh($record, self::SERVICE, ['nin' => $record->nin]);
            $record->refresh();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($record),
        ]);
    }

    private function record(
        User $user,
        string $nin,
        string $status,
        string $result,
        string $comment,
        float $oldBalance,
        float $price,
        ?string $providerId,
        ?string $providerRef,
        bool $refunded = false,
    ): Validation {
        return Validation::create([
            'nin' => $nin,
            'status' => $status,
            'result' => $result,
            'comment' => $comment,
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'price' => $price,
            'providerId' => $providerId,
            'providerRef' => $providerRef,
            // Stamped when the charge was already reversed here, so the admin
            // refund button cannot pay a second time.
            'refundedAt' => $refunded ? now() : null,
            'refundAmount' => $refunded ? $price : null,
            'service' => self::SERVICE,
            'userId' => $user->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Validation $record): array
    {
        return [
            'id' => $record->id,
            'nin' => $record->nin,
            'status' => $record->status,
            'result' => $record->result,
            'comment' => $record->comment,
            'submitted_at' => $record->createdAt?->toIso8601String(),
            'updated_at' => $record->updatedAt?->toIso8601String(),
        ];
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
