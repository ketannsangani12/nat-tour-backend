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
            $table->tinyInteger('global_rate')->default(0)->after('max_group_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->dropColumn('global_rate');
        });
    }
};
