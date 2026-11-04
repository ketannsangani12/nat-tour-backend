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
        Schema::create('booking_tour_guides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('booking_id')->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');
            $table->unsignedBigInteger('guide_id')->foreign('guide_id')->references('id')->on('tour_operator_staff')->onDelete('cascade');
            $table->decimal('distribution_percentage', 10, 2)->default(0.00);
            $table->decimal('distribution_amount', 10, 2)->default(0.00);
            $table->smallInteger('tourists')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_tour_guides');
    }
};
