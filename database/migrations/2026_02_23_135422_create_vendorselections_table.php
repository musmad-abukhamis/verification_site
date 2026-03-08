<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorselectionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendorselections', function (Blueprint $table) {
            $table->string('id')->primary(); // stores network IDs like MTN, AIRTEL, GLO, 9MOBILE
            $table->string('SME')->default('1');
            $table->string('SME2')->default('1');
            $table->string('CORPORATE_GIFTING')->default('1');
            $table->string('CORPORATE_GIFTING2')->default('1');
            $table->string('DATASHARE')->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendorselections');
    }
}