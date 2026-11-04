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
        Schema::table('tour_promotions', function (Blueprint $table) {
            $table->integer('min_allowed_tourists')->default(0)->after('max_allowed_usages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_promotions', function (Blueprint $table) {
            $table->dropColumn('min_allowed_tourists');
        });
    }
};
