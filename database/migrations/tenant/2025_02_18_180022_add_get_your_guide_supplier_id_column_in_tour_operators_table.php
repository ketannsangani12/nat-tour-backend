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
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->string('get_your_guide_supplier_id', 20)->nullable()->after('tripadvisor_reseller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_operators', function (Blueprint $table) {
            $table->dropColumn('get_your_guide_supplier_id');
        });
    }
};
