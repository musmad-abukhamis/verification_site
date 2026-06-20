<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prisma models: Transactions, wallethistory, Plan, networks,
 * vendorselection, vendorapi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('network');
            $table->string('name');
            $table->integer('price');
            $table->string('type');
            $table->string('phone');
            $table->double('oldbal');
            $table->double('newbal');
            $table->string('status');
            $table->string('userId');
            $table->text('response');
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('wallethistory', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->string('status');
            $table->string('fundingtype');
            $table->double('amount');
            $table->double('oldbal');
            $table->double('newbal');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('Plan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('network');
            $table->string('name');
            $table->integer('price');
            $table->integer('agentPrice')->default(1000);
            $table->integer('apiPrice')->default(1000);
            $table->string('type');
            $table->string('validity');
            $table->string('status')->default('on');
            $table->string('planStatus')->default('on');
            $table->integer('apiKey')->nullable()->unique();
            $table->string('vendorPlan1')->default('000');
            $table->string('vendorPlan2')->default('000');
            $table->string('vendorPlan3')->default('000');
            $table->string('vendorPlan4')->default('000');
            $table->string('vendorPlan5')->default('000');
        });

        Schema::create('networks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('vendor1network')->default('1');
            $table->string('vendor2network')->default('1');
            $table->string('vendor3network')->default('1');
            $table->string('vendor4network')->default('1');
            $table->string('vendor5network')->default('1');
        });

        Schema::create('vendorselection', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('SME')->default('1');
            $table->string('SME2')->default('1');
            $table->string('CORPORATE_GIFTING')->default('1');
            $table->string('CORPORATE_GIFTING2')->default('1');
            $table->string('DATASHARE')->default('1');
        });

        Schema::create('vendorapi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('vendor1url')->default('0');
            $table->string('vendor1key')->default('0');
            $table->string('vendor2url')->default('0');
            $table->string('vendor2key')->default('0');
            $table->string('vendor3url')->default('0');
            $table->string('vendor3key')->default('0');
            $table->string('vendor4url')->default('0');
            $table->string('vendor4key')->default('0');
            $table->string('vendor5url')->default('0');
            $table->string('vendor5key')->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendorapi');
        Schema::dropIfExists('vendorselection');
        Schema::dropIfExists('networks');
        Schema::dropIfExists('Plan');
        Schema::dropIfExists('wallethistory');
        Schema::dropIfExists('Transactions');
    }
};
