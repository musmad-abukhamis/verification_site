<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prisma models: User, Account, VerificationToken, PasswordResetToken,
 * TwoFactorToken, TwoFactorConfirmation, Pin, PinVerificationLog, OTP.
 * Table/column names are preserved exactly as Prisma generates them so existing
 * Postgres data can be imported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->double('balance')->default(0);
            $table->string('username')->unique();
            $table->timestamp('email_verified')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('USER');
            $table->text('image')->nullable();
            $table->string('apitoken')->nullable()->unique();
            $table->boolean('isTwoFactorEnabled')->default(false);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->nullable()->useCurrent();
            // Not part of the Prisma schema, but required by Laravel's
            // "remember me" auth. Nullable, so it never blocks data import.
            $table->rememberToken();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('type');
            $table->string('provider');
            $table->string('provider_account_id');
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->integer('expires_at')->nullable();
            $table->string('token_type')->nullable();
            $table->string('scope')->nullable();
            $table->text('id_token')->nullable();
            $table->string('session_state')->nullable();

            $table->unique(['provider', 'provider_account_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('VerificationToken', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('expires');

            $table->unique(['email', 'token']);
        });

        Schema::create('PasswordResetToken', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('expires');

            $table->unique(['email', 'token']);
        });

        Schema::create('TwoFactorToken', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('expires');

            $table->unique(['email', 'token']);
        });

        Schema::create('TwoFactorConfirmation', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId')->unique();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('Pin', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('hashedPin');
            $table->string('userId')->unique();
            $table->integer('failedAttempts')->default(0);
            $table->timestamp('lastFailedAttempt')->nullable();
            $table->boolean('isLocked')->default(false);
            $table->timestamp('lockExpiresAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
            $table->timestamp('lastChangedAt')->useCurrent();
            $table->timestamp('expiresAt')->nullable();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('PinVerificationLog', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->boolean('success');
            $table->string('ipAddress')->nullable();
            $table->string('userAgent')->nullable();
            $table->timestamp('createdAt')->useCurrent();
        });

        Schema::create('OTP', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId')->unique();
            $table->string('code');
            $table->timestamp('expiresAt');
            $table->integer('attempts')->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OTP');
        Schema::dropIfExists('PinVerificationLog');
        Schema::dropIfExists('Pin');
        Schema::dropIfExists('TwoFactorConfirmation');
        Schema::dropIfExists('TwoFactorToken');
        Schema::dropIfExists('PasswordResetToken');
        Schema::dropIfExists('VerificationToken');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');
    }
};
