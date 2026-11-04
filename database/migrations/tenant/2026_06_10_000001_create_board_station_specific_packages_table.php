<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_station_specific_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id');
            $table->json('packages');
            $table->string('time_from');
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_station_specific_packages');
    }
};
