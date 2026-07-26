<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring data_transaction_attempts up to the shape verification_attempts already
 * has, so Admin > Data (VTU) > Vendor Calls can show the same thing the
 * verification module's Provider Calls page does.
 *
 * vendor_name is denormalized on purpose: vendor_id carries no foreign key, so a
 * deleted vendor would otherwise leave rows that name nobody, and the whole
 * point of the audit is reading history after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_transaction_attempts', function (Blueprint $table) {
            $table->string('vendor_name')->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('message')->nullable();

            // Drives the per-vendor 24h health panel and the vendor filter.
            $table->index(['vendor_id', 'created_at']);
        });

        // Backfill names for rows written before this column existed. Attempts
        // predating it keep null http_status/duration_ms/message -- those were
        // never captured and cannot be reconstructed.
        //
        // A correlated subquery rather than Postgres' UPDATE ... FROM: the test
        // suite runs this migration on SQLite.
        DB::statement('
            UPDATE data_transaction_attempts
            SET vendor_name = (
                SELECT name FROM vendors WHERE vendors.id = data_transaction_attempts.vendor_id
            )
            WHERE vendor_name IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('data_transaction_attempts', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'created_at']);
            $table->dropColumn(['vendor_name', 'http_status', 'duration_ms', 'message']);
        });
    }
};
