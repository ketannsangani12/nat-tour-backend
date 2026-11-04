<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auto_payout_run_logs')) {
            return;
        }

        Schema::create('auto_payout_run_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->enum('outcome', [
                'skipped_disabled',
                'skipped_no_rule',
                'skipped_no_enabled_rule',
                'skipped_lock_held',
                'processed',
                'error',
            ]);
            $table->text('message')->nullable();
            $table->unsignedInteger('payouts_created')->default(0);
            $table->unsignedInteger('payouts_executed')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_payout_run_logs');
    }
};
