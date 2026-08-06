<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Models\BvnRetrieval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BVN retrieval for API resellers.
 *
 * Recovers the BVN behind an 8-digit BMS ticket id from an enrolment. Like
 * modification this is fulfilled by our staff, so the submission is charged up
 * front and the BVN itself arrives on the record later — `bvn` is null until
 * an admin completes the request.
 */
class BvnRetrievalController extends Controller
{
    use Concerns\FulfilledService;

    private const SERVICE = 'bvn.retrieve.id';

    /**
     * Submit a retrieval request against a ticket id.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // `bms_id` is what the web form calls it; both spellings are the
            // same 8-digit ticket id from the enrolment.
            'ticket_id' => ['required_without:bms_id', 'string', 'regex:/^\d{8}$/'],
            'bms_id' => ['required_without:ticket_id', 'string', 'regex:/^\d{8}$/'],
        ]);

        $ticketId = $validated['ticket_id'] ?? $validated['bms_id'];

        $user = $request->user();
        $oldBalance = (float) $user->balance;

        [$price, $refusal] = $this->charge($user, self::SERVICE, 'bvn_retrieval');

        if ($refusal) {
            return $refusal;
        }

        try {
            $record = BvnRetrieval::create([
                // The enrolee's details are what the retrieval produces, so the
                // placeholder columns the web form writes are kept as-is rather
                // than asked of an integrator who cannot know them yet.
                'firstname' => '-',
                'middlename' => null,
                'surname' => '-',
                'phone' => '-',
                'retrievalType' => 'id',
                'bvn' => '',
                'ticketId1' => '',
                'ticketId2' => $ticketId,
                'batchId' => '',
                'nin' => '',
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $user->balance,
                'status' => 'pending',
                'comment' => null,
                'userId' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $this->refund($user, $price);
            report($e);

            return $this->error('submission_failed', 'The request could not be filed. You were not charged.', 502);
        }

        return $this->accepted($record, $price, $this->present($record));
    }

    /**
     * The caller's own requests, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BvnRetrieval::where('userId', $request->user()->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $this->listing($query, fn (BvnRetrieval $r) => $this->present($r), (int) $request->input('limit', 50));
    }

    /**
     * One request, by the id we returned or by the ticket id sent.
     */
    public function show(Request $request, string $submission): JsonResponse
    {
        $query = BvnRetrieval::where('userId', $request->user()->id);

        // Ids are cuids, so an 8-digit string is unambiguously a ticket id.
        $record = preg_match('/^\d{8}$/', $submission)
            ? $query->where('ticketId2', $submission)->orderByDesc('createdAt')->first()
            : $query->find($submission);

        if (! $record) {
            return $this->error('not_found', 'No retrieval request found for that id.', 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($record),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BvnRetrieval $r): array
    {
        return [
            'id' => $r->id,
            'ticket_id' => $r->ticketId2,
            'status' => $r->status,
            // Empty until an admin completes the request. Reported as null
            // rather than "" so "not yet" is not mistaken for a blank BVN.
            'bvn' => $r->bvn ?: null,
            'comment' => $r->comment,
            'submitted_at' => $r->createdAt?->toIso8601String(),
            'updated_at' => $r->updatedAt?->toIso8601String(),
        ];
    }
}
