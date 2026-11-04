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
        Schema::table('surcharge_dates', function (Blueprint $table) {
            $table->unsignedBigInteger('tour_package_id')->nullable()->after('id');
            $table->unsignedBigInteger('affiliate_id')->nullable()->after('tour_package_id');
            $table->string('custom_date_name')->nullable()->after('affiliate_id');
            $table->string('date_range_start')->nullable()->after('date');
            $table->string('date_range_end')->nullable()->after('date_range_start');
            $table->tinyInteger('apply_on_slots')->default(0)->after('date_range_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_dates', function (Blueprint $table) {
            $table->dropColumn(['tour_package_id', 'affiliate_id', 'start_date', 'end_date', 'apply_on_slots']);
        });
    }
};
