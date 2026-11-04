<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ALTER TABLE tour_bookings_transact MODIFY `nat_fees_percentage` DECIMAL(6,2);
     */
    public function up(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->float('nat_fees_percentage', 6, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->float('nat_fees_percentage', 5, 2)->change();
        });
    }
};
