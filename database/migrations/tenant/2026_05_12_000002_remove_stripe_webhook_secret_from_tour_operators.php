<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the per-tenant stripe_webhook_secret column from tour_operators.
     *
     * The webhook secret is now stored in the central settings table and read
     * by StripeWebhookController using the central DB connection. The per-tenant
     * column is no longer read by any code.
     *
     * Run this migration AFTER the central settings migration and code deploy.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tour_operators', 'stripe_webhook_secret')) {
            Schema::table('tour_operators', function (Blueprint $table) {
                $table->dropColumn('stripe_webhook_secret');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tour_operators', 'stripe_webhook_secret')) {
            Schema::table('tour_operators', function (Blueprint $table) {
                $table->string('stripe_webhook_secret')
                    ->nullable()
                    ->after('cancellation_within_days');
            });
        }
    }
};
