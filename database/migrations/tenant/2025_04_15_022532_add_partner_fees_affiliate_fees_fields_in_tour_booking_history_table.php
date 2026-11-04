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
        Schema::table('tour_booking_history', function (Blueprint $table) {
            $table->decimal('partner_fees',7,2)->default(0.00)->nullable()->comment('Total Partner fees')->after('platform_fees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_history', function (Blueprint $table) {
            $table->dropColumn('partner_fees');
        });
    }
};
