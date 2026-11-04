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
        if (!Schema::hasColumn('tour_packages', 'disable_slot_overlap_blocking')) {
            Schema::table('tour_packages', function (Blueprint $table) {
                $table->tinyInteger('disable_slot_overlap_blocking')->default(0)->comment('0-No, 1-Yes')->after('is_partner_package');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('disable_slot_overlap_blocking');
        });
    }
};
