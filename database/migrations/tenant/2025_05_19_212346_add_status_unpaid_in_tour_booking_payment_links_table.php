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
        DB::statement("ALTER TABLE `tour_booking_payment_links` CHANGE `status` `status` ENUM('Unpaid', 'Paid', 'Cancelled', 'Expired') NOT NULL DEFAULT 'Unpaid';");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `tour_booking_payment_links` CHANGE `status` `status` ENUM('Unpaid', 'Paid', 'Cancelled', 'Expired') NOT NULL DEFAULT 'Unpaid';");
    }
};
