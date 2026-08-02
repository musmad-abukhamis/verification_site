<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIN validation and IPE clearance are jobs that finish days or hours after they
 * are submitted, and both tables were built as if they finished immediately.
 * Four things have to survive the wait:
 *
 *   providerId  — which provider accepted the job. Only that provider can be
 *                 asked how it went, so without this a status check has nowhere
 *                 to go once more than one provider is configured.
 *   providerRef — the upstream reference, for reconciling by hand when a job is
 *                 disputed. ArewaSmart returns one; Robost does not.
 *   price       — what the user was actually charged. Prices are role-aware and
 *                 admin-editable, so by the time a week-old job is refunded the
 *                 current price may be a different number entirely.
 *   refundedAt  — set once, so the admin refund button cannot pay twice.
 *
 * Column names are camelCase to match the Prisma-ported neighbours in these two
 * tables (oldBal, newBal, userId, createdAt).
 */
return new class extends Migration
{
    /** The two async job tables, and the column each one keys its job on. */
    private const TABLES = ['validation', 'ipe'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->string('providerId')->nullable()->after('userId');
                $blueprint->string('providerRef')->nullable()->after('providerId');
                $blueprint->double('price')->nullable()->after('newBal');
                $blueprint->timestamp('refundedAt')->nullable()->after('price');
                $blueprint->double('refundAmount')->nullable()->after('refundedAt');

                // Every status check filters by "still open", and the admin
                // queues do the same.
                $blueprint->index(['status', 'createdAt'], $table.'_status_created_index');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_status_created_index');
                $blueprint->dropColumn([
                    'providerId', 'providerRef', 'price', 'refundedAt', 'refundAmount',
                ]);
            });
        }
    }
};
