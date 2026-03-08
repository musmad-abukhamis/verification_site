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
        Schema::create('nin_ipe_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nin', 11);
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->text('result')->nullable();
            $table->text('comment')->nullable();
            $table->decimal('old_balance', 12, 2)->default(0);
            $table->decimal('new_balance', 12, 2)->default(0);
            $table->string('reference')->unique();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('nin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nin_ipe_clearances');
    }
};
