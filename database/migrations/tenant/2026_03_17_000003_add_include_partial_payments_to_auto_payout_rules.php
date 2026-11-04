<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_payout_rules', function (Blueprint $table) {
            $table->boolean('include_partial_payments')->default(false)->after('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('auto_payout_rules', function (Blueprint $table) {
            $table->dropColumn('include_partial_payments');
        });
    }
};
