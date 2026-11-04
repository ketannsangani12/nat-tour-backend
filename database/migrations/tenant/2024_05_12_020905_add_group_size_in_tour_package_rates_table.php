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
            $table->integer('min_group_size')->default(0)->after('max_pax_allowed');
            $table->integer('max_group_size')->default(0)->after('min_group_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_rates', function (Blueprint $table) {
            $table->dropColumn(['min_group_size', 'max_group_size']);
        });
    }
};
