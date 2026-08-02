<?php

namespace App\Services\Nin;

use App\Models\VerificationProvider;
use App\Services\Verification\ServiceCatalog;
use App\Services\Verification\VerificationDispatcher;
use App\Services\Verification\VerificationOutcome;
use App\Services\Verification\WorkStatusNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Submitting and polling the two NIN services that take real-world time: IPE
 * clearance (30 minutes–3 hours) and NIN validation (3 days–1 week).
 *
 * The shape of both is the same and differs from every other verification in
 * the app. A lookup asks a question and the answer is the response. These file a
 * job: the response only confirms it was accepted, and the actual result arrives
 * later, when the user presses Check on the row.
 *
 * Two rules follow from that, and they are the reason this class exists rather
 * than the controllers calling the dispatcher directly:
 *
 *  1. A submission is never a completion. However enthusiastic the acceptance
 *     reply is — ArewaSmart answers `"status": "successful"` for a validation
 *     that has not been looked at yet — the record is parked as `processing`.
 *     Only a status check may close it.
 *
 *  2. A status check goes back to the provider that took the job, and asking is
 *     not the same as being answered. If the provider cannot be reached the
 *     record is left exactly as it was; the previous implementation guessed
 *     "completed" here, which told users their clearance was done whenever the
 *     upstream API was down.
 */
class AsyncJobService
{
    public function __construct(
        private readonly VerificationDispatcher $dispatcher,
        private readonly WorkStatusNormalizer $statusNormalizer,
    ) {}

    /**
     * File a job with the provider chain.
     *
     * The chain stops at the first provider (see ServiceCatalog's `failover`
     * flag), so this is really "the highest-priority provider that implements
     * the service" — but it goes through the dispatcher so routing, the attempt
     * log and the usable/credentialled checks all behave as they do elsewhere.
     *
     * @param  array<string, mixed>  $input
     * @param  array{user_id?: string|null, reference?: string|null}  $context
     */
    public function submit(string $service, array $input, array $context = []): VerificationOutcome
    {
        return $this->dispatcher->verify($service, $input, $context);
    }

    /**
     * Ask the provider how a filed job is going, and update the record.
     *
     * @param  Model  $record  a Validation or Ipe row (see AsyncNinJob)
     * @param  string  $submitService  the service it was filed under
     * @param  array<string, mixed>  $input  the identifier to poll with
     */
    public function refresh(Model $record, string $submitService, array $input): AsyncJobResult
    {
        $current = (string) $record->status;

        if ($record->isTerminal()) {
            return AsyncJobResult::updated($current, $record->comment, changed: false);
        }

        $statusService = ServiceCatalog::statusService($submitService);

        if ($statusService === null) {
            return AsyncJobResult::unreachable($current, 'This service cannot be checked automatically.');
        }

        $outcome = $this->poll($record, $statusService, $input);

        // An ambiguous reply — timeout, connection error, HTML error page, 5xx —
        // says nothing about the job. Leave the record alone and say so.
        if ($outcome->isTimeout()) {
            return AsyncJobResult::unreachable(
                $current,
                $outcome->message ?? 'The provider did not respond. Please try again shortly.',
            );
        }

        // Note that a `fail` outcome is *not* skipped: a provider answering
        // "Previous Clearance Failed" has given a real verdict, and the body it
        // came in is exactly what the normalizer needs to read. Only the state
        // of the job matters here, never whether the HTTP call was a success.
        $reading = $this->statusNormalizer->normalize($outcome->raw, $current);

        // Nothing in the reply described a job. That is an error envelope — no
        // provider configured, an auth rejection, a shape we do not know — and
        // reporting it as "still processing" would hide a broken integration
        // behind a message that looks like normal waiting.
        if (! $reading['recognised']) {
            return AsyncJobResult::unreachable(
                $current,
                $outcome->message ?? 'The provider did not report a status for this request.',
            );
        }

        return $this->apply($record, $reading, $outcome);
    }

    /**
     * Send the status request, pinned to the provider holding the job.
     *
     * @param  array<string, mixed>  $input
     */
    private function poll(Model $record, string $statusService, array $input): VerificationOutcome
    {
        $context = ['user_id' => $record->userId, 'reference' => $record->providerRef];

        $provider = $record->providerId
            ? VerificationProvider::find($record->providerId)
            : null;

        if ($provider) {
            return $this->dispatcher->verifyWithProvider($provider, $statusService, $input, $context);
        }

        // Rows submitted before providers were recorded, or whose provider has
        // been deleted. The chain is the only option left, and since these
        // services do not fail over it resolves to a single provider anyway.
        return $this->dispatcher->verify($statusService, $input, $context);
    }

    /**
     * Write the reading onto the record.
     *
     * @param  array{status: string, detail: ?string, reply: ?string, terminal: bool, recognised: bool}  $reading
     */
    private function apply(Model $record, array $reading, VerificationOutcome $outcome): AsyncJobResult
    {
        $status = $reading['status'];
        $detail = $reading['detail'];

        $changed = $status !== (string) $record->status;

        $update = [
            'status' => $status,
            'comment' => $detail ?? $record->comment,
        ];

        // `result` is what the record page and any slip render. Keep the whole
        // reply for a finished job so nothing is lost, but do not overwrite a
        // stored result with a "still processing" envelope.
        if ($reading['terminal']) {
            $update['result'] = json_encode($outcome->raw);
        } elseif ($reading['reply']) {
            $update['result'] = $reading['reply'];
        }

        if ($outcome->providerId && ! $record->providerId) {
            $update['providerId'] = $outcome->providerId;
        }

        $record->update($update);

        return AsyncJobResult::updated($status, $detail, $changed);
    }
}
