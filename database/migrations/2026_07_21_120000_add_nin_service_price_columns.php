<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phone and demographic verification get their own price fields, and the NIN
 * services move onto ninServicePrices -- the table Admin > Service Prices
 * actually edits -- instead of verifyapiconfiq.
 *
 * The backfill is the important part: without it every NIN service would read
 * an empty column the moment this deploys and refuse to run until an admin
 * re-entered prices that were already configured.
 */
return new class extends Migration
{
    /** ninServicePrices column => the verifyapiconfiq column it used to be priced from. */
    private const BACKFILL = [
        'searchslip1' => 'pullingprice',
        'ipe' => 'ipeprice',
        'validation' => 'validation',
        // Phone and demo verification both charged getSlipPrice('premium'),
        // so that is what their new fields start at.
        'phone_verify' => 'premiumslipprice',
        'demo_verify' => 'premiumslipprice',
    ];

    public function up(): void
    {
        Schema::table('ninServicePrices', function (Blueprint $table) {
            $table->string('phone_verify')->nullable();
            $table->string('demo_verify')->nullable();
        });

        $config = DB::table('verifyapiconfiq')->where('id', 'API1')->first();

        if (! $config) {
            return;
        }

        DB::table('ninServicePrices')->insertOrIgnore(['id' => 'API1']);

        foreach (self::BACKFILL as $target => $source) {
            $value = $config->{$source} ?? null;

            if ($value === null) {
                continue;
            }

            // Only fill what is genuinely unset -- an admin who has already
            // entered a price on the Service Prices page keeps it.
            DB::table('ninServicePrices')
                ->where('id', 'API1')
                ->where(fn ($query) => $query->whereNull($target)->orWhere($target, ''))
                ->update([$target => (string) $value]);
        }
    }

    public function down(): void
    {
        Schema::table('ninServicePrices', function (Blueprint $table) {
            $table->dropColumn(['phone_verify', 'demo_verify']);
        });
    }
};
