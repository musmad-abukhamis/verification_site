<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funding payments that arrived but could not be matched to a user.
 *
 * Not part of the Prisma port -- new table. Before this existed the webhooks
 * logged the failure and returned 200, so the provider never retried and the
 * money survived only as a line in laravel.log.
 *
 * `reference` is unique and is the provider's transaction reference -- the same
 * value used as the `wallethistory` primary key when a payment IS attributed.
 * That is what makes resolution idempotent in both directions: a resolved row
 * writes its ledger entry keyed on this reference, so a later webhook retry for
 * the same payment short-circuits on the existing ledger row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_payments', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('provider');                          // payvessel | billstack
            $table->string('reference')->unique();
            $table->string('account_number')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();

            $table->decimal('amount', 15, 2)->default(0);        // gross, as sent
            $table->decimal('settlement_amount', 15, 2)->nullable();

            // The whole webhook body, so a payment can still be reconciled if
            // the fields we picked out turn out to be the wrong ones.
            $table->json('payload')->nullable();

            $table->string('status')->default('pending');        // pending | resolved | ignored
            $table->string('resolved_user_id')->nullable();      // who was credited
            $table->string('resolved_by')->nullable();           // which admin did it
            $table->timestamp('resolved_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unattributed_payments');
    }
};
