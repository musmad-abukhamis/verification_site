<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NIN\NinWalletTrait;
use App\Models\Ipe;
use App\Models\User;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NIN IPE (Identity Proof of Enrollment) clearance for API resellers.
 *
 * Runs the same `nin.ipe` routing chain, price and refund rules as the web
 * screen, so an integrator is billed their own rate and a failed submission
 * costs them nothing.
 *
 * IPE is a *submission*, not a lookup, and that shapes the whole endpoint. An
 * ambiguous upstream reply may mean the request landed, so the dispatcher never
 * re-sends it to another provider -- and neither may the integrator. That case
 * is reported as its own 202 rather than folded into an error the caller would
 * reasonably retry, which would file the clearance twice.
 */
class IpeController extends Controller
{
    use NinWalletTrait;

    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

    /**
     * Submit a tracking id for IPE clearance.
     *
     * The tracking id is the whole request. A NIN is what the clearance exists
     * to produce, so asking for one here would be asking for the answer.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Both spellings the web form and the older endpoints accepted.
            'tracking_id' => ['required_without:trkid', 'string', 'size:15'],
            'trkid' => ['required_without:tracking_id', 'string', 'size:15'],
        ]);

        $user = $request->user();
        $price = $this->getIpePrice($user);

        if ($price === null) {
            return $this->error('service_unavailable', 'This service is currently unavailable. Please contact support.', 503);
        }

        if ((float) $user->balance < $price) {
            return $this->error('insufficient_balance', 'Insufficient wallet balance. Please fund your wallet.', 402);
        }

        $trackingId = $validated['tracking_id'] ?? $validated['trkid'];
        $reference = Ipe::generateReference();

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_ipe']);

        try {
            $outcome = $this->dispatcher->verify('nin.ipe', [
                'tracking_id' => $trackingId,
            ], ['user_id' => $user->id, 'reference' => $reference]);
        } catch (\Throwable $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            report($e);

            return $this->error('provider_error', 'The submission could not be sent. You were not charged.', 502, [
                'reference' => $reference,
            ]);
        }

        if ($outcome->isSuccess()) {
            $record = $this->record($user, $trackingId, 'processing', 'Pending', "[{$reference}] Submitted to ".($outcome->providerName ?? 'provider').' via API', $oldBalance);

            return response()->json([
                'status' => 'success',
                'reference' => $reference,
                'amount' => $price,
                'data' => $this->present($record),
            ], 201);
        }

        // Charge reversed either way. What differs is what the caller may do
        // next, so the two outcomes are never collapsed into one status.
        $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

        if ($outcome->isTimeout()) {
            // Kept on file as `processing`: the clearance may exist upstream, so
            // it has to stay visible for reconciliation.
            $record = $this->record($user, $trackingId, 'processing', 'Unconfirmed', "[{$reference}] ".($outcome->message ?? 'No confirmation from provider'), $oldBalance);

            return response()->json([
                'status' => 'unconfirmed',
                'reference' => $reference,
                'refunded' => true,
                'message' => 'The provider did not confirm this submission. It may still have been filed — do not resubmit. Poll this submission or contact support to reconcile it.',
                'data' => $this->present($record),
            ], 202);
        }

        $record = $this->record($user, $trackingId, 'failed', 'Failed', "[{$reference}] ".($outcome->message ?? 'Submission failed'), $oldBalance);

        return $this->error('submission_failed', $outcome->message ?? 'IPE submission failed.', 422, [
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
        $submissions = Ipe::where('userId', $request->user()->id)
            ->orderByDesc('createdAt')
            ->limit(min((int) $request->input('limit', 50), 200))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ['submissions' => $submissions->map(fn (Ipe $r) => $this->present($r))->all()],
        ]);
    }

    /**
     * One submission, by the id we returned or by the tracking id sent.
     */
    public function show(Request $request, string $submission): JsonResponse
    {
        $query = Ipe::where('userId', $request->user()->id);

        $record = ctype_digit($submission)
            ? $query->find($submission)
            // Same tracking id can be submitted more than once; the latest is
            // the one an integrator polling for a result cares about.
            : $query->where('trkid', $submission)->orderByDesc('createdAt')->first();

        if (! $record) {
            return $this->error('not_found', 'No submission found for that id.', 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($record),
        ]);
    }

    private function record(User $user, string $trackingId, string $status, string $result, string $comment, float $oldBalance): Ipe
    {
        return Ipe::create([
            'trkid' => $trackingId,
            'status' => $status,
            'result' => $result,
            'comment' => $comment,
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'userId' => $user->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Ipe $record): array
    {
        return [
            'id' => $record->id,
            'tracking_id' => $record->trkid,
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
