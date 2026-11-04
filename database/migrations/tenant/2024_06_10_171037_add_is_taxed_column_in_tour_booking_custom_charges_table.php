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
        Schema::table('tour_booking_custom_charges', function (Blueprint $table) {
            $table->tinyInteger('taxable')->default(0)->comment('Whether the amount is taxable or not. 1-Taxed')->after('amount');
            $table->biginteger('parent_id')->nullable()->after('tour_booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_custom_charges', function (Blueprint $table) {
            $table->dropColumn('taxable');
            $table->dropColumn('parent_id');
        });
    }
};
