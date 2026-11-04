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
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->renameColumn('stripe_paymentintent_id', 'external_booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->renameColumn('external_booking_id', 'stripe_paymentintent_id');
        });
    }
};
