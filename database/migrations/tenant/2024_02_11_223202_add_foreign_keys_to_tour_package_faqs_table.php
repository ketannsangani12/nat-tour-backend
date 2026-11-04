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
        Schema::table('tour_package_faqs', function (Blueprint $table) {
            $table->foreign('package_id')
              ->references('id')
              ->on('tour_packages')
              ->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_faqs', function (Blueprint $table) {
            $table->dropForeign('package_id');
        });
    }
};
