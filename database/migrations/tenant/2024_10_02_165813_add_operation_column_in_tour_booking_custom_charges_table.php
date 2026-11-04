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
        Schema::table('tour_booking_custom_charges', function (Blueprint $table) {
            $table->string('operation',20)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_custom_charges', function (Blueprint $table) {
            $table->dropColumn('operation');
        });
    }
};
