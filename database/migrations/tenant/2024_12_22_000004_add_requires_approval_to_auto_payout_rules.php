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
        Schema::table('auto_payout_rules', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(true)->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_payout_rules', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }
};