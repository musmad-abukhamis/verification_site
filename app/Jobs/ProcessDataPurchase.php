<?php

namespace App\Jobs;

use App\Models\Beneficiary;
use App\Models\DataSetting;
use App\Models\DataTransaction;
use App\Models\NetworkVendorMapping;
use App\Models\PlanVendorMapping;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Services\Vendors\VendorDispatcher;
use App\Services\WalletLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fulfils a pending data purchase against the vendor route for its
 * (network, type), with admin-gated failover and automatic refunds.
 *
 * Failover rules (safety first):
 *  - explicit vendor fail → try the next vendor (only if failover is enabled
 *    AND the fail is failover-safe; a 5xx may already have been delivered, so
 *    it ends the run and refunds instead of being retried).
 *  - timeout / ambiguous  → STOP and leave the txn `processing` for
 *    reconciliation. Re-sending could double-deliver. Only a call that got no
 *    response at all lands here; anything the vendor actually answered is
 *    resolved now rather than left pending.
 */
class ProcessDataPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $reference) {}

    public function handle(VendorDispatcher $dispatcher, WalletLedger $ledger): void
    {
        $txn = DataTransaction::find($this->reference);

        // Idempotent: only a freshly-created pending txn is eligible.
        if (! $txn || $txn->status !== 'pending') {
            return;
        }

        $txn->update(['status' => 'processing']);

        $routes = VendorRoute::forRoute($txn->network, $txn->type)
            ->with('vendor')
            ->get()
            ->filter(fn (VendorRoute $r) => $r->vendor && $r->vendor->is_active)
            ->values();

        if ($routes->isEmpty()) {
            $this->refund($txn, $ledger, 'refunded');

            return;
        }

        $vendorIds = $routes->pluck('vendor_id')->all();

        // Two batched lookups (no N+1) for every routed vendor's codes.
        $planCodes = PlanVendorMapping::where('plan_id', $txn->plan_id)
            ->whereIn('vendor_id', $vendorIds)
            ->pluck('external_plan_id', 'vendor_id');
        $networkCodes = NetworkVendorMapping::where('network', $txn->network)
            ->whereIn('vendor_id', $vendorIds)
            ->pluck('external_network_code', 'vendor_id');

        $failoverEnabled = DataSetting::bool('failover_enabled', false);
        $maxAttempts = DataSetting::int('failover_max_attempts', $routes->count());
        if ($maxAttempts <= 0) {
            $maxAttempts = $routes->count();
        }

        $attempts = 0;
        $lastFail = null; // ['vendor_id' => ..., 'raw' => [...]]

        foreach ($routes as $route) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            $vendor = $route->vendor;
            $externalPlan = $planCodes[$vendor->getKey()] ?? null;
            $externalNetwork = $networkCodes[$vendor->getKey()] ?? null;

            $attempts++;
            $txn->increment('attempts');

            // A vendor with no mapping for this plan/network can't be tried —
            // treat as an explicit fail and fall through to failover.
            if ($externalPlan === null || $externalNetwork === null) {
                $lastFail = ['vendor_id' => $vendor->getKey(), 'raw' => ['error' => 'missing vendor mapping']];
                if (! $failoverEnabled) {
                    break;
                }

                continue;
            }

            $result = $dispatcher->purchase($txn, $vendor, $externalPlan, $externalNetwork);

            if ($result->isSuccess()) {
                $txn->update([
                    'status' => 'success',
                    'vendor_id' => $vendor->getKey(),
                    'vendor_reference' => $result->reference,
                    'raw_response' => $result->raw,
                ]);
                $this->saveBeneficiary($txn);

                return;
            }

            if ($result->isTimeout()) {
                // Ambiguous — never fail over. Leave `processing` for reconcile.
                // An async vendor that accepted the order returns its own
                // reference here; without storing it reconciliation has no
                // handle to requery and the purchase can only ever time out
                // into refunded_unconfirmed.
                $txn->update(array_filter([
                    'vendor_id' => $vendor->getKey(),
                    'vendor_reference' => $result->reference,
                    'raw_response' => $result->raw,
                ], fn ($value) => $value !== null));

                // An async vendor that accepted the order usually settles within
                // seconds. Waiting for the next reconcile tick would leave the
                // buyer on a spinner long after the data landed, so poll briefly
                // here and give them the same immediate answer a synchronous
                // vendor would. Reconciliation stays the safety net for anything
                // still unresolved when the budget runs out.
                if ($result->reference) {
                    $this->settleInline($txn, $vendor, $dispatcher, $ledger);
                }

                return;
            }

            // Explicit fail. Terminal either way — the buyer is told — but a
            // fail the vendor may still have delivered (a 5xx) must not be
            // retried elsewhere.
            $lastFail = ['vendor_id' => $vendor->getKey(), 'raw' => $result->raw];

            if (! $failoverEnabled || ! $result->failoverSafe) {
                break;
            }
        }

        // All eligible vendors explicitly failed (or none was usable) → refund.
        if ($lastFail) {
            $txn->update([
                'vendor_id' => $lastFail['vendor_id'],
                'raw_response' => $lastFail['raw'],
            ]);
        }

        $this->refund($txn, $ledger, 'refunded');
    }

    /**
     * Poll an accepted-but-pending order until it resolves or the budget runs
     * out, so the buyer gets a final answer in this request rather than on the
     * next reconcile tick.
     *
     * The budget is deliberately small and bounded: this occupies a queue
     * worker for its duration, and it must finish well inside the worker's
     * per-job timeout (60s by default) or the job is killed mid-flight and
     * retried -- which on a purchase means a second charge.
     *
     * Leaving the transaction `processing` on expiry is the correct outcome,
     * not a failure: reconciliation picks it up unchanged.
     */
    private function settleInline(
        DataTransaction $txn,
        Vendor $vendor,
        VendorDispatcher $dispatcher,
        WalletLedger $ledger,
    ): void {
        $budget = max(0, min(45, DataSetting::int('inline_settle_seconds', 30)));

        if ($budget === 0) {
            return;
        }

        // Fixed iteration count rather than a wall-clock deadline: comparing
        // elapsed time against the budget makes the final poll a race with
        // itself, so a budget equal to one interval would poll or not depending
        // on scheduling jitter.
        $interval = min(5, $budget);
        $polls = max(1, intdiv($budget, $interval));

        for ($i = 0; $i < $polls; $i++) {
            sleep($interval);

            $result = $dispatcher->requery($txn->fresh(), $vendor);

            if ($result->isSuccess()) {
                $txn->update([
                    'status' => 'success',
                    'vendor_reference' => $result->reference ?: $txn->vendor_reference,
                    'raw_response' => $result->raw ?: $txn->raw_response,
                ]);
                $this->saveBeneficiary($txn);

                return;
            }

            if ($result->isFail()) {
                $txn->update(['raw_response' => $result->raw ?: $txn->raw_response]);
                $this->refund($txn, $ledger, 'refunded');

                return;
            }
        }
    }

    private function refund(DataTransaction $txn, WalletLedger $ledger, string $status): void
    {
        $movement = $ledger->credit($txn->user, (float) $txn->price, 'refund', $txn->getKey());

        $txn->update([
            'status' => $status,
            'newbal' => $movement['new'],
        ]);
    }

    /**
     * Auto-save the recipient as a beneficiary (deduped, cap ~10). Persists the
     * ported choice so hints stay suppressed for that number next time.
     */
    private function saveBeneficiary(DataTransaction $txn): void
    {
        Beneficiary::updateOrCreate(
            ['user_id' => $txn->user_id, 'phone' => $txn->phone],
            ['network' => $txn->network, 'is_ported' => (bool) $txn->ported],
        );

        $keep = Beneficiary::where('user_id', $txn->user_id)
            ->latest('updated_at')
            ->limit(10)
            ->pluck('id');

        Beneficiary::where('user_id', $txn->user_id)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
