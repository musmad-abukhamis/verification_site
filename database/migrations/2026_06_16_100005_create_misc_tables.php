<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prisma models: accountkyc, Notification, NotificationUser, IdCard,
 * Settings, Record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accountkyc', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('xixapay_id')->nullable()->unique();
            $table->string('billstack_id')->nullable()->unique();
            $table->string('payvessel_id')->nullable()->unique();
            $table->string('moniepoint')->nullable();
            $table->string('sterling')->nullable();
            $table->string('wema')->nullable();
            $table->string('opay')->nullable();
            $table->string('palmpay')->nullable();
            $table->string('palmpay2')->nullable();
            $table->string('Ninesp')->nullable();
            $table->string('providus')->nullable();
            $table->string('fidelity')->nullable();
            $table->string('status')->default('No-generated');
            $table->string('userId')->unique();
            $table->string('bvn')->default('0')->unique();
            $table->string('nin')->nullable()->default('0');
            $table->string('name')->nullable();
            $table->string('palmpay2_name')->nullable();
            $table->string('ninesp_name')->nullable();
            $table->string('wema_name')->nullable();
            $table->string('firstname')->nullable()->default('name');
            $table->string('surname')->nullable()->default('name');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('message');
            $table->boolean('isEnabled')->default(true);
            $table->integer('duration')->nullable();
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('userId')->nullable();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('notification_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('notificationId');
            $table->boolean('isRead')->default(false);
            $table->boolean('isDismissed')->default(false);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->unique(['userId', 'notificationId']);
            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('notificationId')->references('id')->on('notifications')->cascadeOnDelete();
        });

        Schema::create('IdCard', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('fullname');
            $table->string('email');
            $table->string('agentId');
            $table->binary('passportImage');
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->string('oldBalance');
            $table->string('newBalance');
            $table->string('amountCharged');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->string('userId');

            $table->foreign('userId')->references('id')->on('users');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('site_name')->nullable();
            $table->binary('site_logo_url')->nullable();
            $table->binary('site_logo_url2')->nullable();
            $table->binary('site_logo_url3')->nullable();
            $table->string('site_url')->nullable();
            $table->string('site_email')->nullable();
            $table->string('site_email2')->nullable();
            $table->string('site_phone')->nullable();
            $table->string('site_phone2')->nullable();
            $table->string('whatsapp_url')->nullable();
            $table->string('whatsapp_url2')->nullable();
            $table->string('office_address')->nullable();
            $table->string('office_address2')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('Record', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ticket_id')->unique();
            $table->string('bvn')->nullable();
            $table->string('org_name')->nullable();
            $table->string('org_id')->nullable();
            $table->string('enrollee_name')->nullable();
            $table->string('enroller_id')->nullable();
            $table->string('enroller_id2')->nullable();
            $table->string('msc')->nullable();
            $table->string('msc1')->nullable();
            $table->string('msc2')->nullable();
            $table->string('ticket_id2')->nullable();
            $table->string('status')->nullable();
            $table->text('comment')->nullable();
            $table->double('amount')->nullable();
            $table->string('date_enrolled')->nullable();
            $table->string('timestamp1')->nullable();
            $table->string('timestamp2')->nullable();
            $table->string('timestamp3')->nullable();
            $table->string('time_zone')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Record');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('IdCard');
        Schema::dropIfExists('notification_users');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('accountkyc');
    }
};
