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
            $table->unsignedBigInteger('station_id')->after('guide_id')->nullable();
            $table->string('time_from')->after('station_id')->nullable();
            $table->date('date')->after('time_from')->nullable();
            $table->enum('check_in_status', ['Completed', 'Pending'])->default('Pending')->after('tourists');
            $table->integer('check_in_count')->default('0')->after('check_in_status');

            $table->foreign('station_id')->references('id')->on('stations')->onDelete('cascade');

            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_tour_guides', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
            $table->dropColumn('time_from');
            $table->dropColumn('date');
            $table->dropColumn('check_in_status');
            $table->dropColumn('check_in_count');

            $table->unsignedBigInteger('booking_id')->change();
        });
    }
};
