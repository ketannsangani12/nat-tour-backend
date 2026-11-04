<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which Stripe settlement model a given payment transaction actually used, decided once at
 * PaymentIntent/Checkout-Session-creation time and never rewritten afterward:
 *
 *   - 'destination'    — today's model for every booking: a Stripe destination charge
 *                        (transfer_data.destination + application_fee_amount + on_behalf_of).
 *   - 'platform_held'  — new model, only used going forward for bookings whose tour date is
 *                        2027-01-01 or later: a plain platform charge (on_behalf_of only, no
 *                        transfer_data/application_fee_amount) — see PaymentModeResolver.
 *
 * Purely additive; nothing reads or writes this column until the settlement-mode work is actually
 * wired into the PaymentIntent-creation and refund call sites. The default of 'destination' also
 * correctly backfills every existing row, since every past transaction really was a destination charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'payment_mode')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_mode', ['destination', 'platform_held'])
                ->default('destination')
                ->after('via');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('transactions', 'payment_mode')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
