<?php

namespace App\Services\Verification;

/**
 * Reads a "how is my job going?" reply from any provider and answers in the
 * three states this app stores: processing, completed, failed.
 *
 * This exists because SuccessEvaluator answers a different question. It reports
 * whether the *HTTP call* worked, and for a status poll that is nearly always
 * true even when the job is nowhere near done — ArewaSmart answers
 * `{"success": true, "status": "processing"}`, which SuccessEvaluator correctly
 * calls a successful call and which would be catastrophically wrong to treat as
 * a finished clearance. Job state has to come from the body itself.
 *
 * The two vendors in use disagree about where the answer lives:
 *
 *   ArewaSmart  {"success":true,"status":"processing","comment":"status: New,
 *                reply: SUCCESSFUL, nin: ..., name: MUSA TANKO"}
 *   Robost      {"success":true,"cleared":true,"status":"completed",
 *                "reply":"2GVZ0SI8KO000VK","message":"Clearance Successfull"}
 *   Robost fail {"success":false,"message":"Previous Clearance Failed"}
 *
 * Order of authority: an explicit boolean verdict (`cleared`/`approved`), then a
 * status word, then — only when the provider offered neither — `success: false`
 * as an outright rejection. Anything unrecognised leaves the record alone;
 * "I could not tell" must never be recorded as "done", because a user reading
 * "completed" stops waiting for work that is still in progress.
 */
class WorkStatusNormalizer
{
    /** Status words that mean the job is finished and the work was done. */
    private const DONE = [
        'completed', 'complete', 'completd', 'done', 'success', 'successful',
        'successfull', 'cleared', 'approved', 'validated', 'finished', 'closed',
    ];

    /** Status words that mean the job is finished and the work was not done. */
    private const FAILED = [
        'failed', 'failure', 'rejected', 'declined', 'error', 'invalid',
        'cancelled', 'canceled', 'not found', 'notfound', 'unsuccessful',
    ];

    /** Status words that mean the job is still open. */
    private const PENDING = [
        'processing', 'pending', 'new', 'in_progress', 'inprogress', 'ongoing',
        'submitted', 'queued', 'waiting', 'received', 'open',
    ];

    /** Boolean verdict fields, in order of authority. */
    private const VERDICT_KEYS = ['cleared', 'validated', 'approved', 'is_completed', 'completed'];

    /** Where a status word may hide. */
    private const STATUS_KEYS = ['status', 'job_status', 'jobStatus', 'request_status', 'state'];

    /** Where the human explanation may hide, most specific first. */
    private const DETAIL_KEYS = ['comment', 'reply', 'remark', 'remarks', 'response', 'message', 'detail', 'description'];

    /**
     * @param  array<string, mixed>  $body  the decoded provider response
     * @param  string  $current  the status already on the record, returned when
     *                           the reply is unreadable
     * @return array{status: string, detail: ?string, reply: ?string, terminal: bool, recognised: bool}
     */
    public function normalize(array $body, string $current = 'processing'): array
    {
        $flat = $this->flatten($body);

        $status = $this->resolveStatus($flat);

        return [
            'status' => $status ?? $current,
            'detail' => $this->detail($flat),
            'reply' => $this->stringOrNull($flat['reply'] ?? null),
            'terminal' => $status === 'completed' || $status === 'failed',
            // Null means the body carried no job signal at all — an error
            // envelope, or a provider whose vocabulary is not in the tables
            // above. Callers must not present that as "still processing".
            'recognised' => $status !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    protected function resolveStatus(array $flat): ?string
    {
        // 1. An explicit boolean verdict is the least ambiguous signal a
        //    provider can send, so it wins. `false` here is a real "no", not a
        //    missing key — hence the array_key_exists rather than a truthy test.
        foreach (self::VERDICT_KEYS as $key) {
            if (! array_key_exists($key, $flat) || ! is_bool($flat[$key])) {
                continue;
            }

            // A `false` verdict alongside a pending status word means "not done
            // yet", not "rejected" — the status word disambiguates it.
            if ($flat[$key] === false && $this->statusWord($flat) === 'processing') {
                return 'processing';
            }

            return $flat[$key] ? 'completed' : 'failed';
        }

        // 2. A status word.
        if ($word = $this->statusWord($flat)) {
            return $word;
        }

        // 3. No verdict and no status word: `success: false` is the provider
        //    refusing the job outright (Robost's "Previous Clearance Failed").
        //    `success: true` on its own says the call worked, not the job, so it
        //    is deliberately not treated as completion.
        if (($flat['success'] ?? null) === false) {
            return 'failed';
        }

        return null;
    }

    /**
     * The job state implied by any status-ish field, or null if none said
     * anything recognisable.
     *
     * @param  array<string, mixed>  $flat
     */
    protected function statusWord(array $flat): ?string
    {
        foreach (self::STATUS_KEYS as $key) {
            $value = $flat[$key] ?? null;

            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            $word = strtolower(trim((string) $value));

            if (in_array($word, self::DONE, true)) {
                return 'completed';
            }

            if (in_array($word, self::FAILED, true)) {
                return 'failed';
            }

            if (in_array($word, self::PENDING, true)) {
                return 'processing';
            }
        }

        return null;
    }

    /**
     * The best human-readable explanation in the reply.
     *
     * ArewaSmart packs the useful part into `comment` as a single string
     * ("status: New, validationErrorType: NO RECORD FUND, reply: null"), which
     * is worth keeping verbatim — it is the only place the actual reason for a
     * stalled validation appears.
     *
     * @param  array<string, mixed>  $flat
     */
    protected function detail(array $flat): ?string
    {
        foreach (self::DETAIL_KEYS as $key) {
            if ($value = $this->stringOrNull($flat[$key] ?? null)) {
                return $value;
            }
        }

        return null;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        // Providers write a missing value as the literal string "null" often
        // enough that surfacing it to a user is a real risk.
        return ($value === '' || strtolower($value) === 'null') ? null : $value;
    }

    /**
     * Collapse `data`/`result` envelopes so a status can be read the same way
     * whether the provider wrapped it or not. Outer keys win, because the
     * envelope is where the authoritative verdict sits when both are present.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function flatten(array $body): array
    {
        $flat = [];

        $walk = function (array $node, int $depth) use (&$walk, &$flat) {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    if ($depth < 3) {
                        $walk($value, $depth + 1);
                    }

                    continue;
                }

                $key = (string) $key;

                if (! array_key_exists($key, $flat)) {
                    $flat[$key] = $value;
                }
            }
        };

        $walk($body, 1);

        return $flat;
    }
}
