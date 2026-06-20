<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prisma models: BvnModification, bvnsdkform, bvnserviceprices, bvnRetrieval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BvnModification', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('oldBal')->nullable();
            $table->string('newBal')->nullable();
            $table->string('amountCharged')->nullable();
            $table->string('bvn');
            $table->string('nin');
            $table->text('ninSlipUrl');
            $table->binary('ninSlipImage');
            $table->string('serviceType');
            $table->string('oldFirstName')->nullable();
            $table->string('oldMiddleName')->nullable();
            $table->string('oldLastName')->nullable();
            $table->timestamp('oldDob')->nullable();
            $table->string('oldPhoneNumber')->nullable();
            $table->string('newFirstName')->nullable();
            $table->string('newMiddleName')->nullable();
            $table->string('newLastName')->nullable();
            $table->timestamp('newDob')->nullable();
            $table->string('newPhoneNumber')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('pending');
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('bvnsdkform', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('agentLocation');
            $table->string('agentBvn');
            $table->string('bankName');
            $table->string('accountNumber')->nullable();
            $table->string('accountName');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('email')->unique();
            $table->string('phoneNumber')->unique();
            $table->text('address');
            $table->string('stateOfResidence');
            $table->timestamp('dateOfBirth');
            $table->string('lga');
            $table->string('zone');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('oldBal');
            $table->string('newBal');
            $table->string('status')->nullable();
            $table->text('comment')->nullable();
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('bvnserviceprices', function (Blueprint $table) {
            $table->string('id')->primary()->default('API1');
            $table->string('searchslip1')->nullable();
            $table->string('searchslip2')->nullable();
            $table->string('searchslip3')->nullable();
            $table->string('retrieve_with_phone')->nullable();
            $table->string('retrieve_with_Id')->nullable();
            $table->string('name_mod')->nullable();
            $table->string('phone_mod')->nullable();
            $table->string('dob_mod')->nullable();
            $table->string('email_mod')->nullable();
            $table->string('namedob_mod')->nullable();
            $table->string('namephone_mod')->nullable();
            $table->string('namephonedob_mod')->nullable();
            $table->string('onboarding1')->nullable();
            $table->string('onboarding2')->nullable();
            $table->string('idcardfee')->nullable();
        });

        Schema::create('bvnRetrieval', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('surname');
            $table->string('retrievalType')->default('id');
            $table->string('bvn')->default(' ');
            $table->string('ticketId1')->default(' ');
            $table->string('ticketId2')->default(' ');
            $table->string('batchId')->default(' ');
            $table->string('nin')->default(' ');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('oldBal');
            $table->string('newBal');
            $table->string('status')->nullable();
            $table->text('comment')->nullable();
            $table->string('phone')->default('0');
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bvnRetrieval');
        Schema::dropIfExists('bvnserviceprices');
        Schema::dropIfExists('bvnsdkform');
        Schema::dropIfExists('BvnModification');
    }
};
