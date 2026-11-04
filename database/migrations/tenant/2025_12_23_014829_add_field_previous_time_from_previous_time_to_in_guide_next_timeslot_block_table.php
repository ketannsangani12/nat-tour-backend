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
        Schema::table('guide_next_timeslot_block', function (Blueprint $table) {
            $table->string('previous_time_from')->after('time_to');
            $table->string('previous_time_to')->after('previous_time_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guide_next_timeslot_block', function (Blueprint $table) {
            $table->string('previous_time_from')->after('time_to');
            $table->string('previous_time_to')->after('previous_time_from');
        });
    }
};
