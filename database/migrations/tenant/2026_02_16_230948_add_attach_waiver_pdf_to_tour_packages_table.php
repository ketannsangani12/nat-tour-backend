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
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->tinyInteger('attach_waiver_pdf')->default(0)->after('waiver_content');
            $table->text('waiver_pdf')->after('attach_waiver_pdf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('attach_waiver_pdf');
            $table->dropColumn('waiver_pdf');
        });
    }
};
