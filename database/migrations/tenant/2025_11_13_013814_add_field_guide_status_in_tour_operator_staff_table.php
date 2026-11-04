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
            $table->enum('guide_status', ['Active', 'Inactive'])->default('Inactive')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operator_staff', function (Blueprint $table) {
            $table->dropColumn('guide_status');
        });
    }
};
