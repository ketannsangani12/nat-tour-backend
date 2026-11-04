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
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->tinyInteger('is_cash_rate')->default(0)->after('is_infant_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->dropColumn('is_cash_rate');
        });
    }
};
