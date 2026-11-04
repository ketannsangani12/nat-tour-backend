<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            // Needed to query orphaned transactions (from hard-deleted addon fields) by booking.
            // Allows computing retained fees for addons removed with ticket-cost-only or dont_refund.
            if (!Schema::hasColumn('tour_booking_addon_transactions', 'tour_booking_id')) {
                $table->unsignedBigInteger('tour_booking_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->dropColumn('tour_booking_id');
        });
    }
};
