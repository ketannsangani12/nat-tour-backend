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
        Schema::table('tour_slot_times', function (Blueprint $table) {
            $table->integer('hide_slot')->default(0)->after('bookable_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_slot_times', function (Blueprint $table) {
            $table->dropColumn(['hide_slot']);
        });
    }
};
