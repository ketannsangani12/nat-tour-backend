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
        Schema::table('affilaite_seats_updates', function (Blueprint $table) {
            $table->string('check_in_not')->after('tourists')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affilaite_seats_updates', function (Blueprint $table) {
            $table->dropColumn('check_in_not');
        });
    }
};
