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
        Schema::create('slip_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 'standard', 'premium', 'basic'
            $table->string('name'); // 'Standard Slip', 'Premium Slip'
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('component_name'); // 'StandardSlip', 'PremiumSlip'
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slip_types');
    }
};
