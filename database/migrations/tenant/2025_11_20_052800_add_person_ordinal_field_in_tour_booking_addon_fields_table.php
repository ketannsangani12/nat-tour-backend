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
        Schema::table('tour_booking_addon_fields', function (Blueprint $table) {
            $table->unsignedInteger('person_ordinal')->nullable()->after('pax_index')->comment('The ordinal number of the person (1 for first person, 2 for second, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_addon_fields', function (Blueprint $table) {
            $table->dropColumn('person_ordinal');
        });
    }
};
