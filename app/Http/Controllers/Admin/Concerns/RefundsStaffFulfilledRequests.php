<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin refund for a request charged on submission and fulfilled by staff
 * (models using {@see \App\Models\Concerns\RefundableByAdmin}).
 *
 * Deliberately separate from status updates: rejecting records an outcome,
 * refunding moves money, and the two do not always go together. The amount is
 * editable for partial refunds but capped at what was charged, and `refundedAt`
 * makes it once-only.
 */
trait RefundsStaffFulfilledRequests
{
    protected function refundRequest(Request $request, Model $record, string $label)
    {
        $charged = $record->chargedAmount();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max($charged, 0.01)],
            'reason' => 'nullable|string|max:255',
        ]);

        if (! $record->isRefundable()) {
            return back()->withErrors([
                'message' => $record->refundedAt
                    ? 'This request was already refunded on '.$record->refundedAt->format('d M Y H:i').'.'
                    : 'There is nothing to refund on this request.',
            ]);
        }

        $user = $record->user;

        if (! $user) {
            return back()->withErrors(['message' => 'The user for this request no longer exists.']);
        }

        $amount = round((float) $validated['amount'], 2);

        // The credit and the stamp go together: a crash between them would
        // either pay twice on retry or lose the record of having paid. The row
        // lock stops two admins clicking at once from both getting through.
        $refunded = DB::transaction(function () use ($record, $user, $amount, $validated) {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->refundedAt !== null) {
                return false;
            }

            $user->credit($amount, false, [
                'fundingtype' => 'refund',
                'status' => 'refund',
            ]);

            $locked->update([
                'refundedAt' => now(),
                'refundAmount' => $amount,
                // `nullable` omits the key entirely when the field is absent.
                'comment' => ($validated['reason'] ?? null)
                    ? 'Refunded: '.$validated['reason']
                    : $locked->comment,
            ]);

            return true;
        });

        if (! $refunded) {
            return back()->withErrors(['message' => 'This request was already refunded.']);
        }

        Log::info("[admin] {$label} refunded", [
            'record_id' => $record->getKey(),
            'amount' => $amount,
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', '₦'.number_format($amount, 2).' refunded to '.$user->name.'.');
    }

    /**
     * The refund fields every admin row/detail payload carries.
     *
     * @return array<string, mixed>
     */
    protected function refundPayload(Model $record): array
    {
        return [
            'charged_amount' => $record->chargedAmount(),
            'is_refundable' => $record->isRefundable(),
            'refunded_at' => $record->refundedAt?->format('Y-m-d H:i'),
            'refund_amount' => $record->refundAmount !== null ? (float) $record->refundAmount : null,
        ];
    }
}
