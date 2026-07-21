<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The public plan id: a short 1-3 digit number external integrators quote.
 *
 * The internal `id` is an auto-increment primary key -- fine inside the app,
 * but it is also a moving target and long-term unbounded, and it is the wrong
 * thing to publish. `code` is the stable, short number developers put in their
 * own plan tables.
 *
 * Backfilled as code = id, so every plan id already quoted to an integrator
 * keeps meaning the same plan. Existing ids are well under 999.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('code')->nullable()->after('id');
        });

        DB::table('plans')->update(['code' => DB::raw('id')]);

        // Anything above the 3-digit range cannot be a code; give those the
        // lowest free numbers instead. On a fresh install there are none.
        $overflow = DB::table('plans')->where('code', '>', 999)->orderBy('id')->pluck('id');

        if ($overflow->isNotEmpty()) {
            $taken = DB::table('plans')->where('code', '<=', 999)->pluck('code')->all();
            $next = 1;

            foreach ($overflow as $id) {
                while (in_array($next, $taken, true)) {
                    $next++;
                }

                DB::table('plans')->where('id', $id)->update(['code' => $next]);
                $taken[] = $next++;
            }
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('code')->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
