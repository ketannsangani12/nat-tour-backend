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
            $table->tinyInteger('is_infant_rate')->default(0)->after('is_group_rate');
            $table->unsignedSmallInteger('infants')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->dropColumn('is_infant_rate');
            $table->dropColumn('infants');
        });
    }
};
