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
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->boolean('field_additional_fee')->default(false)->after('application_fee_id')->comment('Additional fee enabled for field');
            $table->boolean('option_additional_fee')->default(false)->after('field_additional_fee')->comment('Additional fee enabled for option');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_addon_transactions', function (Blueprint $table) {
            $table->dropColumn(['field_additional_fee', 'option_additional_fee']);
        });
    }
};
