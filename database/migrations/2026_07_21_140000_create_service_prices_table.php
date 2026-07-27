<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Per-service, per-role pricing.
 *
 * Not part of the Prisma port -- new table. It replaces the one-column-per-
 * service layout of ninServicePrices / verifyapiconfiq, which had nowhere to
 * put a second price for the same service. Buy-data solved this with extra
 * columns (Plan.agent_price, api_price); doing that here would mean five
 * columns per service on an already-wide row, so the axis becomes rows instead.
 *
 * One row per (service, role). `role` is the sentinel 'DEFAULT' for the base
 * price everyone pays, or a UserRole value for an override. The sentinel exists
 * because Postgres treats NULLs as distinct, so a nullable `role` could not be
 * covered by the unique index and nothing would stop two base rows.
 *
 * `is_active` is a real column rather than "price is null" so switching a
 * service off no longer discards its price.
 */
return new class extends Migration
{
    /**
     * service key => [source table, source column, price if the column is null].
     *
     * The fallback column matters more than it looks. The old code charged
     * `pullingprice ?? 50` and friends, so a NULL column did not mean "not
     * offered" -- it meant the service ran at that hardcoded default, and on a
     * real install several of these columns have never been written. Backfilling
     * NULL as "switched off" would take those services down on deploy, so the
     * old defaults are carried across instead.
     *
     * Slips have no fallback: a NULL slip column really did hide the slip from
     * the selector, so off is the faithful translation.
     */
    private const BACKFILL = [
        'nin.verify' => ['ninServicePrices', 'searchslip1', 50],
        'nin.phone' => ['ninServicePrices', 'phone_verify', 100],
        'nin.demographic' => ['ninServicePrices', 'demo_verify', 100],
        'nin.ipe' => ['ninServicePrices', 'ipe', 50],
        'nin.validation' => ['ninServicePrices', 'validation', 50],
        'slip.regular' => ['verifyapiconfiq', 'regslipprice', null],
        'slip.standard' => ['verifyapiconfiq', 'standardslipsprice', null],
        'slip.premium' => ['verifyapiconfiq', 'premiumslipprice', null],
        'slip.nvs' => ['verifyapiconfiq', 'nvsslipprice', null],
        'slip.advanced' => ['verifyapiconfiq', 'advslipprice', null],
    ];

    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('service');
            // 'DEFAULT', or one of ADMIN|USER|AGENT|API|SMART.
            $table->string('role')->default('DEFAULT');
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['service', 'role']);
        });

        $this->backfill();
    }

    /**
     * Seed the base ('DEFAULT') rows from whatever each service currently
     * charges, so this deploy does not change a single price.
     */
    private function backfill(): void
    {
        $sources = [];
        $now = now();
        $rows = [];

        foreach (self::BACKFILL as $service => [$table, $column, $fallback]) {
            if (! array_key_exists($table, $sources)) {
                $sources[$table] = DB::table($table)->where('id', 'API1')->first();
            }

            $value = $sources[$table]->{$column} ?? null;
            $price = is_numeric($value) ? (float) $value : $fallback;

            $rows[] = [
                'id' => (string) Str::uuid(),
                'service' => $service,
                'role' => 'DEFAULT',
                // No stored price and no old default means the service was not
                // on offer, so it starts switched off. The 0 is a placeholder --
                // is_active is what callers actually read.
                'price' => $price ?? 0,
                'is_active' => $price !== null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('service_prices')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
