<?php

namespace App\Services\Frontend;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\Payout;
use App\Services\Frontend\TourBookingAddonService;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FrontendCardBookingService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $transactionId, $historyId, $paymentMethod, $paymentIntent)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);

        $bookingData = TourBooking::find($booking['booking_id']);

		$transaction = Transaction::where('id', $transactionId)->first();

        $payout = Payout::where('tour_booking_id', $bookingData->id)
			->where('transaction_id', $transactionId)
			->where('payout_status', 'Completed')
			->first();

        if ($tourBookingHistory && $bookingData && $transaction) {
            $history = json_decode($tourBookingHistory->details, true);

            $tourBookingHistory->ticket_cost = $transaction->amount - $transaction->fee_amount;
            $tourBookingHistory->discount = $booking['discount2_value'];
            $tourBookingHistory->platform_fees = $transaction->fee_amount;
            $tourBookingHistory->partner_fees = $payout->commission_amount ?? 0;
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = 'Card';
            $tourBookingHistory->card_last_4_digit = $history['card_number'] ?? '';
            $tourBookingHistory->payment_method = $paymentMethod;
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();

            TourBookingAddonService::markAddonsAsPaid((int) $booking['booking_id'], $paymentIntent, 'card');
        }
    }

    /**
     * Update the parent reserved booking
     *
     * @param array $booking
     */
    public static function updateBooking($booking) : void
    {
        $extendedBooking = TourBooking::with('tourSlotTime.tourSlotDate')->find($booking['booking_id']);

        $originalBooking = TourBooking::whereHas('tourSlotTime', function ($query) use ($extendedBooking) {
                $query->where('id', $extendedBooking->tourSlotTime->id);
            })
            ->doesntHave('transactions')
            ->where('tour_package_id', $extendedBooking->tour_package_id)
            ->where('is_reserved_for_affiliate', 1)
            ->where('customer_id', $extendedBooking->customer_id)
            ->where('status', '!=', 'Cancelled')
            ->first();

        if (isset($extendedBooking) && isset($originalBooking)) {
            if ($extendedBooking->tourists == $originalBooking->tourists) {
                static::cancelOriginalBooking($extendedBooking, $originalBooking);
            } else {
                static::updateOriginalBooking($extendedBooking, $originalBooking);
            }
        }
    }

    /**
     * Cancel the parent reserved booking and update the current booking
     *
     * @param object $extendedBooking
     * @param object $originalBooking
     */
    public static function cancelOriginalBooking($extendedBooking, $originalBooking) : void
    {
        $user = Customer::find($extendedBooking->customer_id);
        $created_by_with_name = $user->name . ': (' . "Affiliate" . ')';

        DB::table('tour_booking_history')
            ->insert([
                [
                    'tour_booking_id' => $originalBooking->id,
                    'tourists' => $extendedBooking->tourists,
                    'operation' => 'Split Booking Original',
                    'details' => json_encode([
                        'booking_id' => $originalBooking->id,
                        'extended_booking_id' => $extendedBooking->id,
                        'payment_type' => 'Card',
                        'created_by_with_name' => $created_by_with_name
                    ], JSON_NUMERIC_CHECK),
                    'operation_at' => mysqlDT(),
                    'operation_by' => $extendedBooking->customer_id,
                    'operation_by_user_type' => 'Affiliate',
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ],
                [
                    'tour_booking_id' => $extendedBooking->id,
					'tourists' => $extendedBooking->tourists,
					'operation' => 'Split Booking',
					'details' => json_encode([
                        'booking_id' => $extendedBooking->id,
                        'original_booking_id' => $originalBooking->id,
                        'payment_type' => 'Card',
                        'created_by_with_name' => $created_by_with_name
                    ], JSON_NUMERIC_CHECK),
					'operation_at' => mysqlDT(),
					'operation_by' => $extendedBooking->customer_id,
					'operation_by_user_type' => 'Affiliate',
					'updated_at' => Carbon::now(),
					'created_at' => Carbon::now(),
                ],
                [
                    'tour_booking_id' => $originalBooking->id,
                    'tourists' => $extendedBooking->tourists,
                    'operation' => 'Cancelled',
                    'details' => json_encode([
                        'amount' => 0,
                        'payment_type' => '',
                        'created_by_with_name' => $created_by_with_name,
                        'note' => 'Cancelled by the system',
                        'refund_type' => 'don\'t refund'
                    ], JSON_NUMERIC_CHECK),
                    'operation_at' => mysqlDT(),
                    'operation_by' => $extendedBooking->customer_id,
                    'operation_by_user_type' => 'Affiliate',
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]
            ]);

        $originalBooking->status = 'Cancelled';
        $originalBooking->updated_by = $extendedBooking->customer_id;
        $originalBooking->updated_by = 'Affiliate';
        $originalBooking->save();
    }

    /**
     * Update the parent reserved booking and current booking
     *
     * @param object $extendedBooking
     * @param object $originalBooking
     */
    public static function updateOriginalBooking($extendedBooking, $originalBooking) : void
    {
        $user = Customer::find($extendedBooking->customer_id);
        $created_by_with_name = $user->name . ': (' . "Affiliate" . ')';

        $bookingTransact = BookingTransact::with('packageRate')
            ->where('tour_booking_id', $originalBooking->id)
            ->first();

        DB::table('tour_booking_history')
            ->insert([
                [
                    'tour_booking_id' => $originalBooking->id,
                    'tourists' => $originalBooking->tourists - $extendedBooking->tourists,
                    'operation' => 'Split Booking Original',
                    'details' => json_encode([
                        'booking_id' => $originalBooking->id,
                        'extended_booking_id' => $extendedBooking->id,
                        'payment_type' => 'Card',
                        'created_by_with_name' => $created_by_with_name
                    ], JSON_NUMERIC_CHECK),
                    'operation_at' => mysqlDT(),
                    'operation_by' => $extendedBooking->customer_id,
                    'operation_by_user_type' => 'Affiliate',
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ],
                [
                    'tour_booking_id' => $extendedBooking->id,
					'tourists' => $originalBooking->tourists - $extendedBooking->tourists,
					'operation' => 'Split Booking',
					'details' => json_encode([
                        'booking_id' => $extendedBooking->id,
                        'original_booking_id' => $originalBooking->id,
                        'payment_type' => 'Card',
                        'created_by_with_name' => $created_by_with_name
                    ], JSON_NUMERIC_CHECK),
					'operation_at' => mysqlDT(),
					'operation_by' => $extendedBooking->customer_id,
					'operation_by_user_type' => 'Affiliate',
					'updated_at' => Carbon::now(),
					'created_at' => Carbon::now(),
                ],
                [
                    'tour_booking_id' => $originalBooking->id,
                    'tourists' => $originalBooking->tourists - $extendedBooking->tourists,
                    'operation' => 'Refund',
                    'details' => json_encode([
                        'amount' => 0,
                        'payment_type' => '',
                        'deleted' => $bookingTransact->packageRate->rate_for . ' person ' . $extendedBooking->tourists,
                        'created_by_with_name' => $created_by_with_name,
                        'refund_type' => 'don\'t refund',
                        'from' => 'Extend Booking'
                    ], JSON_NUMERIC_CHECK),
                    'operation_at' => mysqlDT(),
                    'operation_by' => $extendedBooking->customer_id,
                    'operation_by_user_type' => 'Affiliate',
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]
            ]);

        // This var is to calculate the paylater price for new booking pax
        $subtotal = ($bookingTransact->rate + $bookingTransact->additional_charge + $bookingTransact->permit_fee) * $extendedBooking->tourists;

        // Update tour booking transact table
        $bookingTransact->total = $originalBooking->total - $subtotal;
        $bookingTransact->tourists = $originalBooking->tourists - $extendedBooking->tourists;
        $bookingTransact->save();

        // Update tour booking master table
        $originalBooking->total = $bookingTransact->total;
        $originalBooking->booking_total = $bookingTransact->total;
        $originalBooking->tourists = $bookingTransact->tourists;
        $originalBooking->save();
    }
}
