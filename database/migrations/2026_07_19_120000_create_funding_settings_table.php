<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row funding configuration, edited from admin Wallet settings.
 *
 * Not part of the Prisma port -- new table, same single-row convention as
 * verifyapiconfiq / ninServicePrices (string id defaulting to 'API1').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_settings', function (Blueprint $table) {
            $table->string('id')->primary()->default('API1');

            // false: credit the gross amount the customer sent, absorbing the
            //        provider fee (what nimcweb did).
            // true:  credit the settlement amount actually received, so the fee
            //        comes out of the user's funding.
            $table->boolean('credit_net_of_fees')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_settings');
    }
};
