<?php

namespace App\Console\Commands;

use App\Contracts\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedPaymentReservations extends Command
{
    protected $signature = 'transactions:cleanup-abandoned-reservations';

    protected $description = 'Remove stale Initialized transaction reservations where the user abandoned the Make Payment flow';

    /**
     * PaymentIntent statuses Stripe still allows us to cancel.
     */
    private const CANCELABLE_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'requires_capture',
    ];

    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {
                $cutoff = Carbon::now()->subMinutes(30)->toDateTimeString();

                DB::transaction(function () use ($cutoff) {
                    // FOR UPDATE SKIP LOCKED: hold locks through the DELETE so no race
                    // window exists between SELECT and DELETE. Rows currently held by an
                    // active Stripe payment flow are skipped and cleaned up next run.
                    $rows = DB::select(
                        "SELECT id, transaction_number FROM transactions
                        WHERE status = 'Initialized' AND charge_type IS NULL AND created_at < ?
                        FOR UPDATE SKIP LOCKED",
                        [$cutoff]
                    );

                    if (empty($rows)) {
                        return;
                    }

                    $idsToDelete = [];

                    foreach ($rows as $row) {
                        // Never got a PaymentIntent stamped (crashed before the Stripe call) — nothing to cancel.
                        if (empty($row->transaction_number)) {
                            $idsToDelete[] = $row->id;
                            continue;
                        }

                        try {
                            $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($row->transaction_number);
                        } catch (Exception $exception) {
                            // Can't confirm its Stripe status — don't delete blind, try again next run.
                            Log::error($exception);
                            continue;
                        }

                        // The PI actually succeeded but whatever normally flips this row to Completed
                        // hasn't run yet (or failed). Never delete evidence of a real payment based on
                        // local age alone — leave the row and surface it for investigation.
                        if ($paymentIntent->status === 'succeeded') {
                            Log::warning("Abandoned-reservation cleanup found a succeeded PaymentIntent still marked Initialized locally (transaction {$row->id}, PI {$paymentIntent->id}) — leaving it in place.");
                            continue;
                        }

                        // Mid-settlement; it'll resolve to succeeded/failed by the next run.
                        if ($paymentIntent->status === 'processing') {
                            continue;
                        }

                        if (in_array($paymentIntent->status, self::CANCELABLE_STATUSES, true)) {
                            try {
                                app(PaymentGatewayInterface::class)->cancelPayment($paymentIntent->id);
                            } catch (Exception $exception) {
                                // Someone may have confirmed it the instant we checked — don't delete
                                // the local row until we know for sure it's dead.
                                Log::error($exception);
                                continue;
                            }
                        }

                        // Now either freshly canceled above or was already canceled — safe to drop locally.
                        $idsToDelete[] = $row->id;
                    }

                    if (empty($idsToDelete)) {
                        return;
                    }

                    $deleted = DB::table('transactions')->whereIn('id', $idsToDelete)->delete();

                    if ($deleted > 0) {
                        Log::info("Cleaned up {$deleted} abandoned payment reservation(s).");
                    }
                });
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
