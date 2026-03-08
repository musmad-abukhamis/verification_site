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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('network');
            $table->string('name');
            $table->integer('price');
            $table->integer('agentPrice')->default(1000);
            $table->integer('apiPrice')->default(1000);
            $table->string('type');
            $table->string('validity');
            $table->string('status')->default('on');
            $table->string('planStatus')->default('on');
            $table->integer('apiKey')->unique()->nullable();
            $table->string('vendorPlan1')->default('000');
            $table->string('vendorPlan2')->default('000');
            $table->string('vendorPlan3')->default('000');
            $table->string('vendorPlan4')->default('000');
            $table->string('vendorPlan5')->default('000');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};