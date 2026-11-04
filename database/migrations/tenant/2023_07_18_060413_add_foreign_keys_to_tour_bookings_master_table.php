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
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'tour_bookings_master_customer_id')->references(['id'])->on('customers')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign(['tour_package_id'], 'tour_bookings_master_tour_package_id')->references(['id'])->on('tour_packages')->onUpdate('NO ACTION')->onDelete('NO ACTION');
            $table->foreign(['tour_slot_id'], 'tour_bookings_master_tour_slot_time_id')->references(['id'])->on('tour_slot_times')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->dropForeign('tour_bookings_master_customer_id');
            $table->dropForeign('tour_bookings_master_tour_package_id');
            $table->dropForeign('tour_bookings_master_tour_slot_time_id');
        });
    }
};
