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
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->string('partner_fees_type',40)->nullable()->comment('Partner commission type (Fixed / Percentage)')->after('stripe_fees_percentage');
            $table->decimal('partner_fees_percentage',7,2)->default(0.00)->comment('Partner commission Value')->after('partner_fees_type');
            $table->decimal('partner_fees_amount',7,2)->default(0.00)->comment('Calculated Partner commission')->after('partner_fees_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_bookings_transact', function (Blueprint $table) {
            $table->dropColumn('partner_fees_type');
            $table->dropColumn('partner_fees_percentage');
            $table->dropColumn('partner_fees_amount');
        });
    }
};
