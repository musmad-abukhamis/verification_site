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
            // Add new columns
            $table->string('type')->default('direct');
            $table->unsignedInteger('vendor_number')->default(1);
            $table->text('prefixes')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Drop old columns
            $table->dropColumn(['vendor1network', 'vendor2network', 'vendor3network', 'vendor4network', 'vendor5network']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_networks', function (Blueprint $table) {
            // Add back old columns
            $table->string('vendor1network')->nullable();
            $table->string('vendor2network')->nullable();
            $table->string('vendor3network')->nullable();
            $table->string('vendor4network')->nullable();
            $table->string('vendor5network')->nullable();
            
            // Drop new columns
            $table->dropColumn(['type', 'vendor_number', 'prefixes', 'is_active']);
        });
    }
};