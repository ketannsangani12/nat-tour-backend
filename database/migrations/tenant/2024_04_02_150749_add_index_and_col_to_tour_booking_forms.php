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
        Schema::table('tour_booking_forms', function (Blueprint $table) {
            $table->index(["tour_booking_id", "tour_package_form_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_forms', function (Blueprint $table) {
            $table->dropIndex(["tour_booking_id", "tour_package_form_id"]);
        });
    }
};
