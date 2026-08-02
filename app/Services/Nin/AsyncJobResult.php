<?php

namespace App\Services\Nin;

/**
 * What a status check did to a record.
 *
 * `reached` is the field callers care about most: it separates "the provider
 * told us the job is still processing" from "we could not ask the provider".
 * Both leave the record on `processing`, but only the second is an error worth
 * showing the user, and neither may ever be reported as progress.
 */
class AsyncJobResult
{
    private function __construct(
        public readonly bool $reached,
        public readonly bool $changed,
        public readonly string $status,
        public readonly ?string $detail,
        public readonly ?string $message,
    ) {}

    public static function updated(string $status, ?string $detail, bool $changed): self
    {
        return new self(true, $changed, $status, $detail, null);
    }

    /** The provider could not be asked — record untouched. */
    public static function unreachable(string $status, string $message): self
    {
        return new self(false, false, $status, null, $message);
    }

    /** A sentence for the flash message. */
    public function summary(): string
    {
        if (! $this->reached) {
            return $this->message ?? 'Could not reach the provider.';
        }

        $label = match ($this->status) {
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Still processing',
        };

        return $this->detail ? "{$label} — {$this->detail}" : $label;
    }
}
