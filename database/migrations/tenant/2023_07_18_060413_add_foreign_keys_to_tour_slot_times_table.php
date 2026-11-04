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
        Schema::table('tour_slot_times', function (Blueprint $table) {
            $table->foreign(['tour_slot_id'], 'tour_slot_times_tour_slot_id')->references(['id'])->on('tour_slot_dates')->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tour_slot_times', function (Blueprint $table) {
            $table->dropForeign('tour_slot_times_tour_slot_id');
        });
    }
};
