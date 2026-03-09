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
        Schema::table('nin_validations', function (Blueprint $table) {
            $table->string('id_type')->nullable()->after('nin'); // 'nin', 'phone'
            $table->string('id_value')->nullable()->after('id_type'); // The actual NIN or phone number
            $table->string('provider')->nullable()->after('status'); // 'v1', 'v2'
            $table->string('verification_reference')->nullable()->unique()->after('reference');
            $table->decimal('verification_fee', 12, 2)->default(0)->after('new_balance');
            $table->boolean('is_verified')->default(false)->after('verification_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nin_validations', function (Blueprint $table) {
            $table->dropColumn([
                'id_type',
                'id_value',
                'provider',
                'verification_reference',
                'verification_fee',
                'is_verified',
            ]);
        });
    }
};
