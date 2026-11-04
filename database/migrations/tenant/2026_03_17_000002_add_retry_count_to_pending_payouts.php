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
        Schema::table('pending_payouts', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('rejection_reason');
            $table->timestamp('last_failed_at')->nullable()->after('retry_count');
            $table->text('last_failure_reason')->nullable()->after('last_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_payouts', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_failed_at', 'last_failure_reason']);
        });
    }
};
