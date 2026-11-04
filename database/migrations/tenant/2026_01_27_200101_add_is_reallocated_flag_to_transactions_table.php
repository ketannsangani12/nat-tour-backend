<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add is_reallocated flag to transactions table to easily identify
     * transactions related to booking splits and fund reallocation.
     * 
     * This flag helps:
     * - Quickly filter reallocated fund transactions in reports
     * - Avoid parsing JSON data for identification
     * - Improve query performance
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_reallocated')->default(false)->after('status')
                ->comment('Flag to identify if this transaction is related to booking split/fund reallocation');

            // Add index for better query performance when filtering
            $table->index('is_reallocated', 'idx_transactions_is_reallocated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_is_reallocated');
            $table->dropColumn('is_reallocated');
        });
    }
};
