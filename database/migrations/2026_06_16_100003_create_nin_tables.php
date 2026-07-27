<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prisma models: NINDetails, verifyapiconfiq, ipe, validation,
 * personalisation, ninServicePrices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NINDetails', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('surname')->nullable();
            $table->string('othernames')->nullable();
            $table->string('idtype');
            $table->string('idvalue');
            $table->string('sliptype');
            $table->double('oldBal');
            $table->double('newBal');
            $table->integer('price')->nullable();
            $table->string('status')->default('success');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('channel')->nullable();
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('verifyapiconfiq', function (Blueprint $table) {
            $table->string('id')->primary()->default('API1');
            $table->integer('regslipprice')->nullable();
            $table->integer('standardslipsprice')->nullable();
            $table->integer('premiumslipprice')->nullable();
            $table->integer('nvsslipprice')->nullable();
            $table->integer('advslipprice')->nullable();
            $table->integer('ipeprice')->nullable();
            $table->integer('pullingprice')->nullable();
            $table->integer('pullingprice2')->nullable();
            $table->integer('validation')->nullable();
            $table->string('status')->nullable()->default('Active');
        });

        Schema::create('ipe', function (Blueprint $table) {
            $table->increments('id');
            $table->string('trkid');
            $table->text('result');
            $table->string('status')->default('processing');
            $table->string('comment');
            $table->double('oldBal')->nullable();
            $table->double('newBal')->nullable();
            $table->string('userId');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('validation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nin');
            $table->text('result');
            $table->string('status')->default('processing');
            $table->string('comment');
            $table->double('oldBal')->nullable();
            $table->double('newBal')->nullable();
            $table->string('userId');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('personalisation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('trk');
            $table->string('nin')->nullable();
            $table->text('result')->nullable();
            $table->string('status')->default('processing');
            $table->string('comment')->nullable();
            $table->double('oldBal')->nullable();
            $table->double('newBal')->nullable();
            $table->binary('slip')->nullable();
            $table->string('userId');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ninServicePrices', function (Blueprint $table) {
            $table->string('id')->primary()->default('API1');
            $table->string('searchslip1')->nullable();
            $table->string('searchslip2')->nullable();
            $table->string('searchslip3')->nullable();
            $table->string('retrieve_with_phone')->nullable();
            $table->string('retrieve_with_Id')->nullable();
            $table->string('ipe')->nullable();
            $table->string('validation')->nullable();
            $table->string('bankvalidation')->nullable();
            $table->string('adult_enrollent')->nullable();
            $table->string('child_enrollment')->nullable();
            $table->string('photo_error')->nullable();
            $table->string('name_mod')->nullable();
            $table->string('phone_mod')->nullable();
            $table->string('dob_mod')->nullable();
            $table->string('email_mod')->nullable();
            $table->string('namedob_mod')->nullable();
            $table->string('namephone_mod')->nullable();
            $table->string('namephonedob_mod')->nullable();
            $table->string('onboarding1')->nullable();
            $table->string('onboarding2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninServicePrices');
        Schema::dropIfExists('personalisation');
        Schema::dropIfExists('validation');
        Schema::dropIfExists('ipe');
        Schema::dropIfExists('verifyapiconfiq');
        Schema::dropIfExists('NINDetails');
    }
};
