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
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->tinyInteger('checkin')->default(1)->after('bundle_package')->comment('0 - No, 1 - Yes');
            $table->tinyInteger('checkin_type')->default(0)->after('checkin')->comment('0 - Check-in, 1 - Tickets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('checkin');
            $table->dropColumn('checkin_type');
        });
    }
};
