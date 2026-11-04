<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `tour_bookings_master` MODIFY COLUMN `booking_source` ENUM('phone_call','walk_in','email','other','online') NULL AFTER `note`");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `tour_bookings_master` MODIFY COLUMN `booking_source` ENUM('phone_call','walk_in','email','other') NULL AFTER `note`");
    }
};
