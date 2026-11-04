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
        Schema::table('booking_tour_guides', function (Blueprint $table) {
            $table->enum('booking_checked', ['Yes', 'No'])->default('No')->after('check_in_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_tour_guides', function (Blueprint $table) {
            $table->enum('booking_checked', ['Yes', 'No'])->default('No')->after('check_in_count');
        });
    }
};
