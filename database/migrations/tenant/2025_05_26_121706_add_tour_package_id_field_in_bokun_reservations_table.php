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
        Schema::table('bokun_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('tour_package_id')->nullable()->after('id')->index();
            $table->foreign('tour_package_id')->references('id')->on('tour_packages')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bokun_reservations', function (Blueprint $table) {
            $table->dropForeign(['tour_package_id']);
            $table->dropColumn('tour_package_id');
        });
    }
};
