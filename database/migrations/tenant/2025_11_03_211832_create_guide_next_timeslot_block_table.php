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
        Schema::create('guide_next_timeslot_block', function (Blueprint $table) {
            $table->id();
            $table->integer('guide_id');
            $table->unsignedBigInteger('station_id')->foreign('station_id')->references('id')->on('stations')->onDelete('cascade')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('time_from');
            $table->string('time_to');
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guide_next_timeslot_block');
    }
};
