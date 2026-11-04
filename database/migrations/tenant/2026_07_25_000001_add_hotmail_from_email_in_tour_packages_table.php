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
        if (!Schema::hasColumn('tour_packages', 'from_email_hotmail')) {
            Schema::table('tour_packages', function (Blueprint $table) {
                $table->string('from_email_hotmail')->after('from_email')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tour_packages', 'from_email_hotmail')) {
            Schema::table('tour_packages', function (Blueprint $table) {
                $table->dropColumn('from_email_hotmail');
            });
        }
    }
};
