<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `validation` holds two unrelated services.
 *
 * NIN Verification is an instant lookup. NIN Validation is a job filed with
 * NIMC that takes days. They cost different money, mean different things to a
 * user, and both have always been written to this one table with nothing but
 * the free-text `comment` to tell them apart — so the Verification page listed
 * validations, the Validation page listed lookups, and no history screen could
 * honestly answer "what did I spend on verifications".
 *
 * `service` records which one wrote the row, using the same ServiceCatalog keys
 * the pricing and routing engines already use (`nin.verify`, `nin.phone`,
 * `nin.demographic`, `nin.validation`).
 *
 * The backfill classifies existing rows from the marks each writer leaves. The
 * decisive one is that only the async validation writers ever set `price`,
 * `providerId`, `providerRef` or `refundedAt` — a lookup never touches them —
 * so those columns identify a validation regardless of what its comment says.
 * Comment patterns then catch the older validation rows written before those
 * columns existed, and anything still unmatched is a lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation', function (Blueprint $table) {
            $table->string('service')->nullable()->after('userId');

            // Every history tab and both index pages filter on it.
            $table->index(['service', 'createdAt'], 'validation_service_created_index');
        });

        // 1. Positively a validation: an async job, or one of the comment
        //    shapes the pre-async validation writers produced.
        DB::table('validation')
            ->where(function ($q) {
                $q->whereNotNull('price')
                    ->orWhereNotNull('providerId')
                    ->orWhereNotNull('providerRef')
                    ->orWhereNotNull('refundedAt')
                    // "Submitted to X — awaiting result", and the API writer's
                    // "[NIN_123] Submitted to X via API".
                    ->orWhere('comment', 'like', '%Submitted to %')
                    // The stub web controller that predates the provider engine.
                    ->orWhere('comment', 'like', 'Validation request %')
                    ->orWhere('comment', '=', 'Validation completed via API');
            })
            ->update(['service' => 'nin.validation']);

        // 2. Everything else came from a lookup. The method is recoverable from
        //    the comment for the two writers that name it; the rest are NIN.
        DB::table('validation')->whereNull('service')
            ->where(function ($q) {
                $q->where('comment', 'like', '[nin.phone]%')
                    ->orWhere('comment', 'like', '% verify (phone)%')
                    ->orWhere('comment', '=', 'Phone verification successful');
            })
            ->update(['service' => 'nin.phone']);

        DB::table('validation')->whereNull('service')
            ->where(function ($q) {
                $q->where('comment', 'like', '[nin.demographic]%')
                    ->orWhere('comment', 'like', '% verify (demographic)%')
                    ->orWhere('comment', '=', 'Demo verification successful');
            })
            ->update(['service' => 'nin.demographic']);

        DB::table('validation')->whereNull('service')->update(['service' => 'nin.verify']);
    }

    public function down(): void
    {
        Schema::table('validation', function (Blueprint $table) {
            $table->dropIndex('validation_service_created_index');
            $table->dropColumn('service');
        });
    }
};
