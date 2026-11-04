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
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->string('ota_reference_booking', 50)->nullable()->after('get_your_guide_reference_booking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->dropColumn('ota_reference_booking');
        });
    }
};
