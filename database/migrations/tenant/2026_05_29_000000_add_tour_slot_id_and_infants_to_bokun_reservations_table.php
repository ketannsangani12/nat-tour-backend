<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bokun_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('tour_slot_id')->nullable()->after('tour_package_id')->index();
            $table->unsignedSmallInteger('infants')->default(0)->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('bokun_reservations', function (Blueprint $table) {
            $table->dropColumn(['tour_slot_id', 'infants']);
        });
    }
};
