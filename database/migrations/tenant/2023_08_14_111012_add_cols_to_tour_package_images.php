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
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->string('caption', '255')->nullable()->default("")->after('image');
            $table->string('category', '255')->nullable()->default("")->after('caption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_package_images', function (Blueprint $table) {
            $table->dropColumn('caption');
            $table->dropColumn('category');
        });
    }
};
