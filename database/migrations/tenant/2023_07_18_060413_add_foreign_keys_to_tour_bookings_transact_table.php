<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->foreign(['tour_booking_id'], 'tour_booking_transact_tour_booking_id')->references(['id'])->on('tour_bookings_master')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign(['tour_package_rate_id'], 'tour_booking_transact_tour_package_rate_id')->references(['id'])->on('tour_package_rates')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->dropForeign('tour_booking_transact_tour_booking_id');
            $table->dropForeign('tour_booking_transact_tour_package_rate_id');
        });
    }
};
