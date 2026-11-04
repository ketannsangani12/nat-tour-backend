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
        Schema::create('affilaite_seats_updates', function (Blueprint $table) {
            $table->id();
            $table->string('tour_booking_id');
            $table->string('tour_package_id');
            $table->string('tour_slot_id');
            $table->string('customer_id');
            $table->string('tourists')->default(0);
            $table->string('check_in_1st')->default(0);
            $table->string('check_in_2nd')->default(0);
            $table->string('absent')->default(0);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affilaite_seats_updates');
    }
};
