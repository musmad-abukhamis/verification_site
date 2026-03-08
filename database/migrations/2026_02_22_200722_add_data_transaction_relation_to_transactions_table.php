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
        Schema::table('transactions', function (Blueprint $table) {
            // Add data_transaction relationship fields if they don't exist
            $table->string('network')->nullable()->after('type');
            $table->string('phone')->nullable()->after('network');
            $table->decimal('oldbal', 15, 2)->nullable()->after('phone');
            $table->decimal('newbal', 15, 2)->nullable()->after('oldbal');
            $table->text('response')->nullable()->after('newbal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['network', 'phone', 'oldbal', 'newbal', 'response']);
        });
    }
};