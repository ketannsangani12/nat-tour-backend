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
        Schema::create('pending_payout_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pending_payout_id');
            $table->unsignedBigInteger('booking_id');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('pending_payout_id')->references('id')->on('pending_payouts')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('tour_bookings_master')->onDelete('cascade');

            // Indexes for performance
            $table->index('pending_payout_id');
            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_payout_items');
    }
};