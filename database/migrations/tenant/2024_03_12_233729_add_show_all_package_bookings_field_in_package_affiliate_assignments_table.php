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
        Schema::table('package_affiliate_assignments', function (Blueprint $table) {
            $table->tinyInteger('show_all_package_bookings')->default(0)->after('affiliate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_affiliate_assignments', function (Blueprint $table) {
            $table->dropColumn(['show_all_package_bookings']);
        });
    }
};
