<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BVN retrieval is settled by staff, not by a provider: a request is charged on
 * submission, then sits until someone finds the BVN — or cannot. Rejecting one
 * already sets its status, but the fee stayed debited, so refunds were being
 * made by hand from the wallet screen with nothing on the row to show it.
 *
 * `refundedAt` is what makes the refund once-only, the same guard the async NIN
 * jobs use: these rows are reviewed days apart, often by different admins, and
 * nothing else distinguishes "refunded" from "not yet". `refundAmount` records
 * what was actually returned, which is not always the whole fee.
 *
 * camelCase to match the Prisma-ported columns already in this table (oldBal,
 * newBal, userId, createdAt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bvnRetrieval', function (Blueprint $table) {
            $table->timestamp('refundedAt')->nullable()->after('newBal');
            $table->double('refundAmount')->nullable()->after('refundedAt');
        });
    }

    public function down(): void
    {
        Schema::table('bvnRetrieval', function (Blueprint $table) {
            $table->dropColumn(['refundedAt', 'refundAmount']);
        });
    }
};
