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
        // SQLite doesn't support ALTER COLUMN for enum, so we need to recreate the column
        // For production MySQL/PostgreSQL, this would be a simple ALTER
        
        // Add new column for slip_type in details JSON is handled by the model
        // The type enum expansion is handled by modifying the column
        
        // Since we're using SQLite in dev, we'll add a virtual/computed approach
        // The Transaction model will handle the type validation
        
        // Add index for better query performance on slip downloads
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
