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
           $table->unsignedBigInteger('tour_package_id')->nullable()->after('tour_slot_id');
           $table->integer('time_slot_seats')->after('tour_package_id');
           $table->date('date')->index()->after('time_slot_seats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_slot_times', function (Blueprint $table) {
            $table->dropColumn('tour_package_id', 'time_slot_seats', 'date');
        });
    }
};
