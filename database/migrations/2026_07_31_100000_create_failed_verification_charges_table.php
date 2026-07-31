<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit + idempotency ledger for the failed-verification charge.
 *
 * Until now a NIN/BVN lookup that the provider answered with "no record" was
 * refunded in full, so a user could burn provider quota for free. The admin can
 * now charge a (separately configured) fee for a provider-confirmed failure —
 * but only for a *confirmed* one: a timeout, a 5xx or an outage is still fully
 * refunded, because the request never got an answer.
 *
 * One row per failed verification, whether or not money moved. The `charged`
 * flag plus `reason` is what the user's history renders, and the row is written
 * for not-charged failures too so "you were NOT charged, because it was a
 * technical error" is a fact on record rather than an absence of one.
 *
 * `verification_reference` is UNIQUE and is the duplicate-charge guard: one
 * verification attempt can only ever produce one row, and therefore one debit,
 * no matter how many times the request is replayed. `wallet_history_id` is the
 * matching `wallethistory` row, whose id is derived from that same reference —
 * so the ledger itself refuses a second insert as well.
 *
 * Deliberately additive: no existing table is altered, so this is safe to run
 * against production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_verification_charges', function (Blueprint $table) {
            $table->id();

            $table->string('user_id');
            // 'nin' | 'bvn' — which toggle/amount applied.
            $table->string('identity_type', 8);
            // ServiceCatalog key, e.g. nin.verify / bvn.verify.
            $table->string('service');
            // The caller's verification reference. UNIQUE: the idempotency key.
            $table->string('verification_reference')->unique();

            // SUCCESS is never stored; the rest are the FailureClassification
            // constants: RECORD_NOT_FOUND, FAILED_VERIFICATION, TIMEOUT,
            // TECHNICAL_ERROR, PROVIDER_ERROR.
            $table->string('classification');

            $table->boolean('charged')->default(false);
            $table->decimal('amount', 12, 2)->default(0);
            // Why nothing was charged (or CHARGED when it was).
            $table->string('reason');

            // The wallethistory row this debit created, and the balances around
            // it — the deduction audit trail asked for, without ever storing the
            // BVN/NIN itself.
            $table->string('wallet_history_id')->nullable();
            $table->double('balance_before')->nullable();
            $table->double('balance_after')->nullable();

            // sha256 of the identity value. Lets us spot a double-submitted form
            // without persisting the number; see the dedupe window in
            // FailedVerificationChargeService.
            $table->string('identity_hash', 64)->nullable();

            $table->string('provider_name')->nullable();
            $table->text('message')->nullable();

            // The history row this failure belongs to (NINDetails.id,
            // validation.id or Transactions.id, depending on the flow) so the
            // user's history can show the charge next to the attempt.
            $table->string('record_id')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['identity_type', 'created_at']);
            $table->index('record_id');
            $table->index(['user_id', 'identity_hash', 'created_at']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_verification_charges');
    }
};
