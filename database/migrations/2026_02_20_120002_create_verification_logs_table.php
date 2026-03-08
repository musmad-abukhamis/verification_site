<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['nin', 'bvn']);
            $table->string('identity_number');
            $table->json('verification_data')->nullable();
            $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('identity_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};
