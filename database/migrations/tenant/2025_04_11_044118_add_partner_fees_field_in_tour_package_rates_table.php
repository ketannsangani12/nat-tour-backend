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
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->decimal('partner_fee_percentage',7,2)->default(0.00)->comment('Partner commission percentage')->after('primary_additional_charge');
            $table->decimal('partner_fee_amount',7,2)->default(0.00)->comment('Partner Commission Amount')->after('partner_fee_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->dropColumn('partner_fee_percentage');
            $table->dropColumn('partner_fee_amount');
        });
    }
};
