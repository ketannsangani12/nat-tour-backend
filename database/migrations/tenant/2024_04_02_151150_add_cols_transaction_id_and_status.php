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
        Schema::table('tour_booking_addons', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->default(null)->after('fee');
            $table->string('status')->default("open")->before('description');
            $table->index(["booking_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_booking_addons', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
            $table->dropColumn('status');
            $table->dropIndex(["booking_id"]);
        });
    }
};
