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
        Schema::create('vendor_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('vendor_plan1')->nullable();
            $table->string('vendor_plan2')->nullable();
            $table->string('vendor_plan3')->nullable();
            $table->string('vendor_plan4')->nullable();
            $table->string('vendor_plan5')->nullable();
            $table->foreign('plan_id')->references('id')->on('data_plans')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_plans');
    }
};