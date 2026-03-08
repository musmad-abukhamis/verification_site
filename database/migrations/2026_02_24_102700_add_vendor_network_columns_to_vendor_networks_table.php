<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_networks', function (Blueprint $table) {
            $table->string('vendor1network')->nullable()->after('network');
            $table->string('vendor2network')->nullable()->after('vendor1network');
            $table->string('vendor3network')->nullable()->after('vendor2network');
            $table->string('vendor4network')->nullable()->after('vendor3network');
            $table->string('vendor5network')->nullable()->after('vendor4network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_networks', function (Blueprint $table) {
            $table->dropColumn(['vendor1network', 'vendor2network', 'vendor3network', 'vendor4network', 'vendor5network']);
        });
    }
};