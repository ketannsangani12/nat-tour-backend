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
        Schema::table('tour_promotions', function (Blueprint $table) {
            $table->tinyInteger('is_extra_charge')->default(0)->after('discount_value_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_promotions', function (Blueprint $table) {
            $table->dropColumn(['is_extra_charge']);
        });
    }
};
