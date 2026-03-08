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
        Schema::create('vendorapi', function (Blueprint $table) {
            $table->id();
            $table->string('vendor1url')->nullable();
            $table->string('vendor1key')->nullable();
            $table->string('vendor2url')->nullable();
            $table->string('vendor2key')->nullable();
            $table->string('vendor3url')->nullable();
            $table->string('vendor3key')->nullable();
            $table->string('vendor4url')->nullable();
            $table->string('vendor4key')->nullable();
            $table->string('vendor5url')->nullable();
            $table->string('vendor5key')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendorapi');
    }
};