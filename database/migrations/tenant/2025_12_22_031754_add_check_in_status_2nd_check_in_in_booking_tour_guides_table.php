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
         DB::statement("ALTER TABLE `booking_tour_guides` CHANGE `check_in_status` `check_in_status` ENUM('Pending', '2nd Check In', 'Completed') NOT NULL DEFAULT 'Pending';");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `booking_tour_guides` CHANGE `check_in_status` `check_in_status` ENUM('Pending', '2nd Check In', 'Completed') NOT NULL DEFAULT 'Pending';");
    }
};
