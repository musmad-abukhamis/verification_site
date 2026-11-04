<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BVN Modification and BVN SDK Onboarding are charged on submission and then
 * worked by staff, exactly like BVN Retrieval. Rejecting one sets its status
 * but leaves the fee debited, so these get the same admin refund.
 *
 * `refundedAt` makes the refund once-only (rows are reviewed days apart, often
 * by different admins); `refundAmount` records what was actually returned,
 * which is not always the whole fee. camelCase to match the Prisma-ported
 * columns already in both tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('BvnModification', function (Blueprint $table) {
            $table->timestamp('refundedAt')->nullable()->after('amountCharged');
            $table->double('refundAmount')->nullable()->after('refundedAt');
        });

        Schema::table('bvnsdkform', function (Blueprint $table) {
            $table->timestamp('refundedAt')->nullable()->after('newBal');
            $table->double('refundAmount')->nullable()->after('refundedAt');
        });
    }

    public function down(): void
    {
        Schema::table('BvnModification', function (Blueprint $table) {
            $table->dropColumn(['refundedAt', 'refundAmount']);
        });

        Schema::table('bvnsdkform', function (Blueprint $table) {
            $table->dropColumn(['refundedAt', 'refundAmount']);
        });
    }
};
