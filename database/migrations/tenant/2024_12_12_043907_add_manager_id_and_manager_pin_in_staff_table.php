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
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->integer('manager_id')->after('status');
            $table->integer('manager_pin')->after('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->dropColumn('manager_id');
            $table->dropColumn('manager_pin');
        });
    }
};
