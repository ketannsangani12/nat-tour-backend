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
            $table->decimal('primary_rate', 10, 2)->default(0)->after('rate');
            $table->decimal('primary_additional_charge', 10, 2)->default(0)->after('additional_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->dropColumn(['primary_rate', 'primary_additional_charge']);
        });
    }
};
