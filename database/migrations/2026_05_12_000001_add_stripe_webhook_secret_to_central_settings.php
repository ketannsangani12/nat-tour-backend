<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add STRIPE_WEBHOOK_SECRET to the central settings table.
     *
     * Seeds the value from the environment so the row is immediately usable
     * after the migration runs — no manual DB edit required.
     *
     * Run this migration BEFORE deploying the code that reads the secret from
     * this table, or ensure the env fallback is still present during the window.
     */
    public function up(): void
    {
        $exists = DB::table('settings')
            ->where('key', 'STRIPE_WEBHOOK_SECRET')
            ->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'key'        => 'STRIPE_WEBHOOK_SECRET',
                'value'      => env('STRIPE_WEBHOOK_SECRET', ''),
                'comment'    => 'Central Stripe webhook signing secret. Used by StripeWebhookController to verify all incoming Stripe events.',
                'input_type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'STRIPE_WEBHOOK_SECRET')
            ->delete();
    }
};
