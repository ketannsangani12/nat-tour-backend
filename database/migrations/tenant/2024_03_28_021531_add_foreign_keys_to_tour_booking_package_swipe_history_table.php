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
        Schema::table('tour_booking_package_swipe_history', function (Blueprint $table) {
			$table->foreign(['tour_booking_id'], 'tour_booking_package_swipe_history_tour_booking_id_foreign')->references(['id'])->on('tour_bookings_master')->onUpdate('NO ACTION')->onDelete('NO ACTION');
			$table->foreign(['old_tour_package_id'], 'tour_booking_package_swipe_history_old_tour_package_id')->references(['id'])->on('tour_packages')->onUpdate('NO ACTION')->onDelete('NO ACTION');
			$table->foreign(['new_tour_package_id'], 'tour_booking_package_swipe_history_new_tour_package_id')->references(['id'])->on('tour_packages')->onUpdate('NO ACTION')->onDelete('NO ACTION');

		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_package_swipe_history', function (Blueprint $table) {
			$table->dropForeign('tour_booking_package_swipe_history_tour_booking_id_foreign');
			$table->dropForeign('tour_booking_package_swipe_history_old_tour_package_id');
			$table->dropForeign('tour_booking_package_swipe_history_new_tour_package_id');
        });
    }
};
