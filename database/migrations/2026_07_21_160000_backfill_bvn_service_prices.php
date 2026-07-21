<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings the BVN services onto service_prices so they get the same per-role
 * pricing as the NIN ones.
 *
 * bvnserviceprices had one column per service and no room for a second price,
 * which is the same limitation service_prices was built to remove -- so BVN
 * moves onto it rather than growing a role column per service (fifteen services
 * times four roles is sixty columns).
 *
 * Unlike the NIN backfill there are no hardcoded fallbacks to preserve: every
 * BVN consumer already treated an unset column as "unavailable" and refused,
 * so a missing price translates straight to a switched-off service.
 */
return new class extends Migration
{
    /** service key => bvnserviceprices column. */
    private const BACKFILL = [
        'bvn.mod.name' => 'name_mod',
        'bvn.mod.dob' => 'dob_mod',
        'bvn.mod.phone' => 'phone_mod',
        'bvn.mod.email' => 'email_mod',
        'bvn.mod.name_dob' => 'namedob_mod',
        'bvn.mod.name_phone' => 'namephone_mod',
        'bvn.mod.name_dob_phone' => 'namephonedob_mod',
        'bvn.search.premium' => 'searchslip1',
        'bvn.search.standard' => 'searchslip2',
        'bvn.search.regular' => 'searchslip3',
        'bvn.retrieve.phone' => 'retrieve_with_phone',
        'bvn.retrieve.id' => 'retrieve_with_Id',
        'bvn.onboarding1' => 'onboarding1',
        'bvn.onboarding2' => 'onboarding2',
        'bvn.idcard' => 'idcardfee',
    ];

    public function up(): void
    {
        $source = DB::table('bvnserviceprices')->where('id', 'API1')->first();
        $now = now();
        $rows = [];

        foreach (self::BACKFILL as $service => $column) {
            // Re-running must not clobber a price an admin has since set.
            if (DB::table('service_prices')->where('service', $service)->where('role', 'DEFAULT')->exists()) {
                continue;
            }

            $value = $source->{$column} ?? null;
            $priced = is_numeric($value);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'service' => $service,
                'role' => 'DEFAULT',
                'price' => $priced ? (float) $value : 0,
                'is_active' => $priced,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('service_prices')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('service_prices')
            ->whereIn('service', array_keys(self::BACKFILL))
            ->delete();
    }
};
