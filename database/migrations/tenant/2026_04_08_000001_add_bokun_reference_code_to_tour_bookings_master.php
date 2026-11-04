<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->string('bokun_reference_code')->nullable()->after('ota_reference_booking')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->dropColumn('bokun_reference_code');
        });
    }
};
