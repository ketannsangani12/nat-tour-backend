<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\PreBooking;
use App\Models\Transaction;
use App\Http\Controllers\API\V2\TourBookingController;
use App\Models\TourBooking;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\DB;

class VerifyPreBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pre-booking:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover successful Stripe payments that never became bookings.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        PreBooking::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->chunkById(100, function ($preBookings) {
                // Log::info($preBookings);
                foreach ($preBookings as $preBooking) {
                    $this->processPreBooking($preBooking);
                }
            });

        return self::SUCCESS;
    }

    /**
     * Process a pre-booking
     *
     * @param PreBooking $preBooking
     * @return void
     */
    protected function processPreBooking(PreBooking $preBooking): void
    {
        try {
            // Enhanced duplicate check - check both transactions and bookings
            $existingTransaction = Transaction::where('transaction_number', $preBooking->payment_intent_id)
                ->where('status', 'Completed')
                ->first();

            $existingBooking = TourBooking::where('stripe_paymentintent_id', $preBooking->payment_intent_id)
                ->where('status', 'Booked')
                ->first();

            if ($existingTransaction || $existingBooking) {
                $preBooking->delete();
                return;
            }

            // Max attempts reached
            if ($preBooking->attempts >= 10) {
                $preBooking->delete();
                return;
            }

            // Stripe verify
            $pi = app(PaymentGatewayInterface::class)->retrievePayment($preBooking->payment_intent_id);
            if ($pi->status !== 'succeeded' || $pi->amount_received <= 0) {
                // still pending → keep row, just increment attempts
                $preBooking->increment('attempts');
                return; // not ready yet; leave pending
            }

            // Build payload
            $payload = is_array($preBooking->data)
                ? $preBooking->data
                : json_decode($preBooking->data, true) ?? [];

            // Idempotent booking create
            DB::transaction(function () use ($payload, $preBooking) {
                $request = new Request();
                $request->merge($payload);
                $response = app(TourBookingController::class)->packageBookingTour($request);
                $responseData = is_array($response)
                    ? $response
                    : (json_decode($response->getContent(), true) ?? []);
                if (empty($responseData['success']) && empty($responseData['bookingIds'])) {
                    throw new \RuntimeException('packageBookingTour failed: ' . ($responseData['message'] ?? 'unknown error'));
                }
                $preBooking->update(['status' => 'booking_created', 'attempts' => $preBooking->attempts + 1]);
            });
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Network / rate-limit → retry later
            Log::warning('Stripe error in VerifyPreBooking', [
                'pre_booking_id' => $preBooking->id,
                'error' => $e->getMessage(),
            ]);
            $preBooking->increment('attempts');
        } catch (Throwable $e) {
            Log::error('VerifyPreBooking error', [
                'pre_booking_id' => $preBooking->id,
                'pi' => $preBooking->payment_intent_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // optional: mark failed after N attempts
            if ($preBooking->attempts >= 10) {
                $preBooking->delete();
            } else {
                $preBooking->increment('attempts');
            }
        }
    }
}
