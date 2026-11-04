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
			$table->string('visitor')->nullable()->default(NULL)->after('status');
			$table->string('request_from')->nullable()->default(NULL)->after('visitor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
			$table->dropColumn('visitor');
			$table->dropColumn('request_from');
        });
    }
};
