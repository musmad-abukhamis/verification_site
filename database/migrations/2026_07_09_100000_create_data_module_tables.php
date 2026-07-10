<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized VTU data-reselling module.
 *
 * Replaces the denormalized vendor1..5 tables (Plan/networks/vendorselection/
 * vendorapi) with a normalized vendor-routing schema, a queued-fulfilment
 * transaction table with an attempt audit, a dedicated wallet_entries ledger,
 * beneficiaries, network prefixes and per-key settings.
 *
 * Money follows the app convention: `double` columns cast to `float`.
 * cuid string PKs come from the App\Models\Concerns\HasPrismaId trait.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Discard the old denormalized data tables. Transactions + wallethistory
        // are shared with NIN/BVN/wallet and are intentionally left in place.
        Schema::dropIfExists('vendorapi');
        Schema::dropIfExists('vendorselection');
        Schema::dropIfExists('networks');
        Schema::dropIfExists('Plan');

        Schema::create('vendors', function (Blueprint $table) {
            $table->string('id')->primary(); // cuid
            $table->string('name');
            $table->string('base_url');
            $table->string('driver'); // token_style_a | token_style_b | oauth
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->smallInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('network');
            $table->string('type');
            $table->string('name');
            $table->double('price')->default(0);
            $table->double('agent_price')->default(0);
            $table->double('api_price')->default(0);
            $table->string('validity')->nullable();
            $table->string('status')->default('on');      // type-level availability
            $table->string('plan_status')->default('on');  // plan visibility
            $table->timestamps();

            $table->index(['network', 'type']);
        });

        Schema::create('plan_vendor_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('plan_id');
            $table->string('vendor_id');
            $table->string('external_plan_id');
            $table->timestamps();

            $table->unique(['plan_id', 'vendor_id']);
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });

        Schema::create('network_vendor_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('network');
            $table->string('vendor_id');
            $table->string('external_network_code');
            $table->timestamps();

            $table->unique(['network', 'vendor_id']);
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });

        // Ordered vendor priority per (network, type). Position 1 is the primary
        // vendor; positions 2+ are failover candidates.
        Schema::create('vendor_routes', function (Blueprint $table) {
            $table->id();
            $table->string('network');
            $table->string('type');
            $table->string('vendor_id');
            $table->smallInteger('position');
            $table->timestamps();

            $table->unique(['network', 'type', 'position']);
            $table->index(['network', 'type']);
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });

        // Admin-editable — Nigeria adds new prefixes regularly.
        Schema::create('network_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('network');
            $table->string('prefix');
            $table->timestamps();

            $table->unique(['network', 'prefix']);
        });

        // Named data_settings to avoid clashing with the existing pricing
        // `settings` table used by the admin ServicePrice screens.
        Schema::create('data_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('data_transactions', function (Blueprint $table) {
            $table->string('id')->primary(); // reference Data_{ms}_{rand6}
            $table->string('user_id');
            $table->unsignedInteger('plan_id')->nullable();
            $table->string('status')->default('pending');
            // pending | processing | success | fail | refunded | refunded_unconfirmed
            $table->string('network');
            $table->string('type');
            $table->string('plan_name');
            $table->double('price');
            $table->string('phone');
            $table->boolean('ported')->default(false);
            $table->string('vendor_id')->nullable();
            $table->string('vendor_reference')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->double('oldbal');
            $table->double('newbal');
            $table->jsonb('raw_response')->nullable();
            $table->string('client_ref')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'client_ref']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Full audit of failover hops (credentials stripped from payloads).
        Schema::create('data_transaction_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('data_transaction_id');
            $table->string('vendor_id');
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response')->nullable();
            $table->string('outcome'); // success | fail | timeout
            $table->timestamp('created_at')->useCurrent();

            $table->index('data_transaction_id');
            $table->foreign('data_transaction_id')->references('id')->on('data_transactions')->cascadeOnDelete();
        });

        // Dedicated ledger for the data module. Every balance mutation writes one
        // row inside the same DB transaction; users.balance is the cached total.
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->string('id')->primary(); // cuid
            $table->string('user_id');
            $table->string('direction'); // credit | debit
            $table->double('amount');
            $table->double('balance_after');
            $table->string('reason'); // purchase | refund | admin_credit | admin_debit | funding
            $table->string('data_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->string('id')->primary(); // cuid
            $table->string('user_id');
            $table->string('phone');
            $table->string('network');
            $table->boolean('is_ported')->default(false);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'phone']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('data_transaction_attempts');
        Schema::dropIfExists('data_transactions');
        Schema::dropIfExists('data_settings');
        Schema::dropIfExists('network_prefixes');
        Schema::dropIfExists('vendor_routes');
        Schema::dropIfExists('network_vendor_mappings');
        Schema::dropIfExists('plan_vendor_mappings');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('vendors');
        // Note: the old Plan/networks/vendorselection/vendorapi tables are not
        // recreated on rollback — re-run the finance migration if needed.
    }
};
