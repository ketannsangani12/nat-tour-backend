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
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_booking_id');
            $table->foreign('tour_booking_id')->references('id')->on('tour_bookings_master');
            $table->unsignedBigInteger('staff_id');
            $table->foreign('staff_id')->references('id')->on('tour_operator_staff');
            $table->unsignedBigInteger('manager_id');
            $table->foreign('manager_id')->references('id')->on('tour_operator_staff');
            $table->enum('status', ['Pending', 'Approved', 'Deny', 'Expired'])->default('Pending');
            $table->enum('request_type', ['Cancel', 'Refund', 'Reschedule', 'Edit Booking']);
            $table->enum('staff_type', ['Tour Operator', 'Affiliate']);
            $table->enum('cancelled_by', ['Staff', 'Manager'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
