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
            $table->string('stripe_paymentintent_id', 191)->nullable()->after('booking_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_master', function (Blueprint $table) {
            $table->dropColumn('stripe_paymentintent_id');
        });
    }
};
