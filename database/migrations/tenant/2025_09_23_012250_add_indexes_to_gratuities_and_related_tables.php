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
        Schema::table('gratuity_distributions', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->default(null)->change();
            $table->unsignedBigInteger('gratuity_id')->nullable()->default(null)->change();
            $table->unsignedBigInteger('guide_id')->nullable()->default(null)->change();
        });
        Schema::table('gratuity_distributions', function (Blueprint $table) {
            $table->index('payroll_schedule_id', 'idx_gd_payroll_schedule');
            $table->index('booking_id', 'idx_gd_booking');
            $table->index('gratuity_id', 'idx_gd_gratuity');
            $table->index('guide_id', 'idx_gd_guide');

            // Composite indexes
            $table->index(['booking_id', 'guide_id'], 'idx_gd_booking_guide');
            $table->index(['gratuity_id', 'guide_id'], 'idx_gd_gratuity_guide');
        });
        Schema::table('gratuities', function (Blueprint $table) {
            $table->index('booking_id', 'idx_g_booking');
            $table->index('guide_id', 'idx_g_guide');
            $table->index('gratuity_type', 'idx_g_type');
            $table->index('created_at', 'idx_g_created_at');

            // Composite indexes
            $table->index(['booking_id', 'guide_id'], 'idx_g_booking_guide');
        });
        Schema::table('booking_tour_guides', function (Blueprint $table) {
            $table->index('booking_id', 'idx_btg_booking');
            $table->index('guide_id', 'idx_btg_guide');
            $table->index(['booking_id', 'guide_id'], 'idx_btg_booking_guide');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gratuity_distributions', function (Blueprint $table) {
            $table->dropIndex('idx_gd_payroll_schedule');
            $table->dropIndex('idx_gd_booking');
            $table->dropIndex('idx_gd_gratuity');
            $table->dropIndex('idx_gd_guide');
            $table->dropIndex('idx_gd_booking_guide');
            $table->dropIndex('idx_gd_gratuity_guide');
        });

        Schema::table('gratuities', function (Blueprint $table) {
            $table->dropIndex('idx_g_booking');
            $table->dropIndex('idx_g_guide');
            $table->dropIndex('idx_g_type');
            $table->dropIndex('idx_g_created_at');
            $table->dropIndex('idx_g_booking_guide');
        });
        Schema::table('booking_tour_guides', function (Blueprint $table) {
            $table->dropIndex('idx_btg_booking');
            $table->dropIndex('idx_btg_guide');
            $table->dropIndex('idx_btg_booking_guide');
        });
    }
};
