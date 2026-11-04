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
        Schema::create('board_station_specific_name', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id')->foreign('station_id')->references('id')->on('stations')->nullable()->onDelete('cascade');
            $table->string('name');
            $table->string('time_from');
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_station_specific_name');
    }
};
