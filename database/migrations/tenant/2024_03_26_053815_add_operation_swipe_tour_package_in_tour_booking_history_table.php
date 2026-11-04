<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		DB::statement("ALTER TABLE `tour_booking_history` CHANGE `operation` `operation` ENUM('Note','Comments','Payment','Refund','Cancelled','Email','Check-in','Booked By','Rescheduled','Waiver','Tip','Added','Update','Restore','Customer Info Update','Payment Type Update', 'Error', 'Reference Booking', 'Card Error', 'Split Booking Original', 'Split Booking', 'Pickup Hotel', 'Tour Guide', 'Switch Tour Package') NOT NULL DEFAULT 'Note';");
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		DB::statement("ALTER TABLE `tour_booking_history` CHANGE `operation` `operation` ENUM('Note','Comments','Payment','Refund','Cancelled','Email','Check-in','Booked By','Rescheduled','Waiver','Tip','Added','Update','Restore','Customer Info Update','Payment Type Update', 'Error', 'Reference Booking', 'Card Error', 'Split Booking Original', 'Split Booking', 'Pickup Hotel', 'Tour Guide') NOT NULL DEFAULT 'Note';");
	}
};
