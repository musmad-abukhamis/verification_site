<?php

namespace App\Jobs;

use App\Models\Beneficiary;
use App\Models\DataSetting;
use App\Models\DataTransaction;
use App\Models\NetworkVendorMapping;
use App\Models\PlanVendorMapping;
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
 *  - explicit vendor fail → try the next vendor (only if failover is enabled).
 *  - timeout / ambiguous  → STOP and leave the txn `processing` for
 *    reconciliation. Re-sending could double-deliver.
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
                $txn->update([
                    'vendor_id' => $vendor->getKey(),
                    'raw_response' => $result->raw,
                ]);

                return;
            }

            // Explicit fail.
            $lastFail = ['vendor_id' => $vendor->getKey(), 'raw' => $result->raw];

            if (! $failoverEnabled) {
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
