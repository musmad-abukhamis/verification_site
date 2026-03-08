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
        Schema::table('vendor_plans', function (Blueprint $table) {
            // Add new columns
            $table->unsignedInteger('vendor_number')->nullable();
            $table->string('vendor_plan_code')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Drop old columns
            $table->dropColumn(['vendor_plan1', 'vendor_plan2', 'vendor_plan3', 'vendor_plan4', 'vendor_plan5']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_plans', function (Blueprint $table) {
            // Add back old columns
            $table->string('vendor_plan1')->nullable();
            $table->string('vendor_plan2')->nullable();
            $table->string('vendor_plan3')->nullable();
            $table->string('vendor_plan4')->nullable();
            $table->string('vendor_plan5')->nullable();
            
            // Drop new columns
            $table->dropColumn(['vendor_number', 'vendor_plan_code', 'is_active']);
        });
    }
};