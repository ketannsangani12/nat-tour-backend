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
            $table->tinyInteger('is_cash_rate')->default(0)->after('is_infant_rate');
            $table->decimal('cash_rate', 7, 2)->default(0)->after('margin')->comment('Cash Rate for the tour package rate');
            $table->decimal('cash_tax', 7, 2)->default(0)->after('cash_rate')->comment('Cash Tax for the tour package rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->dropColumn('is_cash_rate');
            $table->dropColumn('cash_rate');
            $table->dropColumn('cash_tax');
        });
    }
};
