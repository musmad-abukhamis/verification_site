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
        Schema::create('vendor_networks', function (Blueprint $table) {
            $table->id();
            $table->string('network');
            $table->string('vendor1network')->nullable();
            $table->string('vendor2network')->nullable();
            $table->string('vendor3network')->nullable();
            $table->string('vendor4network')->nullable();
            $table->string('vendor5network')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_networks');
    }
};