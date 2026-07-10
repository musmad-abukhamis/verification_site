<?php

namespace App\Jobs;

use App\Models\DataSetting;
use App\Models\DataTransaction;
use App\Models\Vendor;
use App\Services\Vendors\VendorDispatcher;
use App\Services\WalletLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resolves ambiguous (`processing`) purchases. The customer is always made whole
 * automatically — the admin never processes a refund by hand.
 *
 *  - vendor says success           → success
 *  - vendor says failed            → auto-refund → refunded
 *  - still unknown past the cutoff → auto-refund → refunded_unconfirmed
 *    (informational flag for the admin exceptions report; if the vendor later
 *     reports delivery it is surfaced there, never auto re-debited).
 */
class ReconcilePendingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VendorDispatcher $dispatcher, WalletLedger $ledger): void
    {
        $cutoffMinutes = DataSetting::int('reconcile_cutoff_minutes', 120);

        DataTransaction::where('status', 'processing')
            ->orderBy('created_at')
            ->chunkById(100, function ($transactions) use ($dispatcher, $ledger, $cutoffMinutes) {
                foreach ($transactions as $txn) {
                    $this->reconcile($txn, $dispatcher, $ledger, $cutoffMinutes);
                }
            });
    }

    private function reconcile(DataTransaction $txn, VendorDispatcher $dispatcher, WalletLedger $ledger, int $cutoffMinutes): void
    {
        $vendor = $txn->vendor_id ? Vendor::find($txn->vendor_id) : null;

        $result = $vendor ? $dispatcher->requery($txn, $vendor) : null;

        if ($result && $result->isSuccess()) {
            $txn->update([
                'status' => 'success',
                'vendor_reference' => $result->reference ?? $txn->vendor_reference,
                'raw_response' => $result->raw ?: $txn->raw_response,
            ]);

            return;
        }

        if ($result && $result->isFail()) {
            $this->refund($txn, $ledger, 'refunded', $result->raw);

            return;
        }

        // Still ambiguous. Refund once the transaction is older than the cutoff.
        if ($txn->created_at->lt(now()->subMinutes($cutoffMinutes))) {
            $this->refund($txn, $ledger, 'refunded_unconfirmed', $result->raw ?? []);
        }
    }

    private function refund(DataTransaction $txn, WalletLedger $ledger, string $status, array $raw = []): void
    {
        $movement = $ledger->credit($txn->user, (float) $txn->price, 'refund', $txn->getKey());

        $txn->update([
            'status' => $status,
            'newbal' => $movement['new'],
            'raw_response' => $raw ?: $txn->raw_response,
        ]);
    }
}
