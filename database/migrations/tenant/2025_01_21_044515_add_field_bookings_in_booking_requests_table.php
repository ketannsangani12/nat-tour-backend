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
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('tour_booking_id')->nullable()->change();
            $table->unsignedBigInteger('order_id')->after('tour_booking_id')->nullable()->index('booking_requests_order_id_foreign');
            $table->text('bookings')->after('order_id')->nullable();
            $table->text('details')->after('order_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn('order_id');
            $table->dropColumn('bookings');
            $table->dropColumn('details');
        });
    }
};
