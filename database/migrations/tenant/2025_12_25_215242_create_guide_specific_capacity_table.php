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
        Schema::create('guide_specific_capacity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guide_id')->foreign('guide_id')->references('id')->on('tour_operator_staff')->onDelete('cascade');
            $table->unsignedBigInteger('station_id')->foreign('station_id')->references('id')->on('stations')->nullable()->onDelete('cascade');
            $table->string('time_from');
            $table->date('date');
            $table->Integer('capacity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guide_specific_capacity');
    }
};
