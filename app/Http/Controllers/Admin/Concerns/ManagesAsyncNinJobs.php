<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Nin\AsyncJobService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The three things an admin can do to a NIN validation or IPE clearance that is
 * waiting on, or has come back from, a provider.
 *
 * All three exist because these jobs take days (validation) or hours (IPE) and
 * are settled by people, not code: a provider phones in a result, a job stalls
 * with no status endpoint answering, a customer disputes a charge. The automatic
 * status check covers the common path; this covers the rest.
 *
 * Refunds are the part worth being careful about. The amount is editable —
 * partial refunds are a real thing when a provider part-delivers — but it is
 * capped at what the user paid, and `refundedAt` makes it a once-only action.
 * Without that guard the same record could be refunded by two admins on two
 * different days, and nothing in the row would show it had already happened.
 *
 * The three actions are `protected` and take a resolved model, because implicit
 * route-model binding matches on the parameter's *name* and needs a concrete
 * class to resolve — neither of which a shared trait can provide. Each
 * controller exposes them as thin public methods typed to its own model.
 */
trait ManagesAsyncNinJobs
{
    /**
     * The service key these records were submitted under, e.g. `nin.validation`.
     */
    abstract protected function jobService(): string;

    /**
     * The identifier this record is polled with, as canonical inputs.
     *
     * @return array<string, mixed>
     */
    abstract protected function jobInput(Model $record): array;

    /**
     * Overwrite status and result by hand.
     *
     * Deliberately separate from the automatic check: this records what an admin
     * asserts, so it never consults a provider and never touches the wallet. A
     * refund, if one is owed, is the other button.
     */
    protected function settleUpdate(Request $request, Model $record)
    {
        $validated = $request->validate([
            // Read off the record's own class: PHP only exposes a trait's
            // constants through the classes that use it.
            'status' => 'required|string|in:'.implode(',', $record::EDITABLE_STATUSES),
            'result' => 'nullable|string|max:20000',
            'comment' => 'nullable|string|max:255',
        ]);

        $record->update([
            'status' => $validated['status'],
            'result' => $validated['result'] ?? $record->result,
            'comment' => $validated['comment'] ?: $record->comment,
        ]);

        Log::info('[admin] async NIN job status set by hand', [
            'service' => $this->jobService(),
            'record_id' => $record->getKey(),
            'status' => $validated['status'],
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', 'Record updated.');
    }

    /**
     * Run the automatic status check on the user's behalf.
     */
    protected function settleRecheck(Model $record, AsyncJobService $jobs)
    {
        $result = $jobs->refresh($record, $this->jobService(), $this->jobInput($record));

        if (! $result->reached) {
            return back()->withErrors(['message' => $result->summary()]);
        }

        return back()->with('success', 'Provider reports: '.$result->summary());
    }

    /**
     * Return money for a job that failed or was never delivered.
     */
    protected function settleRefund(Request $request, Model $record)
    {
        $charged = $record->chargedAmount();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max($charged, 0.01)],
            'reason' => 'nullable|string|max:255',
        ]);

        if (! $record->isRefundable()) {
            return back()->withErrors([
                'message' => $record->refundedAt
                    ? 'This record was already refunded on '.$record->refundedAt->format('d M Y H:i').'.'
                    : 'There is nothing to refund on this record.',
            ]);
        }

        $user = $record->user;

        if (! $user) {
            return back()->withErrors(['message' => 'The user for this record no longer exists.']);
        }

        $amount = round((float) $validated['amount'], 2);

        // The credit and the stamp go together: a crash between them would
        // either pay twice on retry or lose the record of having paid.
        DB::transaction(function () use ($record, $user, $amount, $validated) {
            $user->credit($amount, false, [
                'fundingtype' => 'refund',
                'status' => 'refund',
            ]);

            $record->update([
                'refundedAt' => now(),
                'refundAmount' => $amount,
                'comment' => $validated['reason']
                    ? 'Refunded: '.$validated['reason']
                    : $record->comment,
            ]);
        });

        Log::info('[admin] async NIN job refunded', [
            'service' => $this->jobService(),
            'record_id' => $record->getKey(),
            'amount' => $amount,
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', '₦'.number_format($amount, 2).' refunded to '.$user->name.'.');
    }

    /**
     * The refund/override fields every admin row carries.
     *
     * @return array<string, mixed>
     */
    protected function jobAdminPayload(Model $record): array
    {
        return [
            'charged_amount' => $record->chargedAmount(),
            'is_refundable' => $record->isRefundable(),
            'refunded_at' => $record->refundedAt?->format('Y-m-d H:i'),
            'refund_amount' => $record->refundAmount !== null ? (float) $record->refundAmount : null,
            'provider' => $record->provider?->name,
            'provider_ref' => $record->providerRef,
        ];
    }
}
