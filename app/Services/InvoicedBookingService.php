<?php

namespace App\Services;

use App\Models\BookingTransact;
use App\Models\TourBookingHistory;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\CustomChargeBookingService;

class InvoicedBookingService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $transactionId, $historyId, $paymentMethod, $request)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);

        $previousSplitPayment = TourBookingHistory::where('tour_booking_id', $booking->id)
            ->where('payment_method', 'Split Payment')
            ->count();

        $discount = TourBookingHistory::where('tour_booking_id', $booking->id)->sum('discount');

        if ($tourBookingHistory && !isset($request['is_pax_added'])) {
            if ($paymentMethod === 'Full Payment' && $previousSplitPayment === 0) {
                $history = json_decode($tourBookingHistory->details, true);

                $tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

                $permitFeesTotal = 0;
                foreach ($tourBookingTransact as $pax) {
                    $permitFee = $pax->tourists * $pax->permit_fee;
                    $permitFeesTotal += $permitFee;
                }

                // check if discount is applied in the transaction or not
                $discountedAmount = 0;
                if (isset($request['discount_type']) || ($discount == 0 && $booking->discount2_value > 0)) {
                    $discountedAmount = $booking->discount2_value;
                }

                $tourBookingHistory->ticket_cost = $history['amount'];
                $tourBookingHistory->discount = $discountedAmount;
                $tourBookingHistory->permit_fees = $permitFeesTotal;
                $tourBookingHistory->transaction_id = $transactionId;
                $tourBookingHistory->payment_type = 'Invoiced';
                $tourBookingHistory->payment_method = $paymentMethod;
                $tourBookingHistory->save();

                // Store custom charge
				CustomChargeBookingService::createBookingStoreCustomCharge($booking, $tourBookingHistory->payment_type);
            }

            if ($paymentMethod === 'Split Payment' || $previousSplitPayment > 0) {
                // override the full payment text to split payment text if any previous split payment did by user
                $paymentMethod = ($paymentMethod == 'Full Payment') ? 'Full Payment' : 'Split Payment';

                $history = json_decode($tourBookingHistory->details, true);

                $tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

                $ticketCostTotal = 0;
                $permitFeesTotal = 0;
                foreach ($tourBookingTransact as $pax) {
                    $ticketCost = $pax->tourists * ($pax->rate + $pax->additional_charge + $pax->permit_fee);
                    $ticketCostTotal += $ticketCost;

                    $permitFee = $pax->tourists * $pax->permit_fee;
                    $permitFeesTotal += $permitFee;
                }

                $total = $ticketCostTotal - $booking->discount2_value;

                // count the percentage of payment done from total amount to calculate permit fee
                $paidAmountPercentage = $history['amount'] / $total * 100;

                // check if discount is applied in the transaction or not
                $discountedAmount = 0;
                if (isset($request['discount_type']) || ($discount == 0 && $booking->discount2_value > 0)) {
                    $discountedAmount = $booking->discount2_value;
                }

                $tourBookingHistory->ticket_cost = $history['amount'];
                $tourBookingHistory->discount = $discountedAmount;
                $tourBookingHistory->permit_fees = $permitFeesTotal * $paidAmountPercentage / 100;
                $tourBookingHistory->transaction_id = $transactionId;
                $tourBookingHistory->payment_type = 'Invoiced';
                $tourBookingHistory->payment_method = $paymentMethod;
                $tourBookingHistory->is_processed = 1;
                $tourBookingHistory->save();
            }
        }

        if (!isset($request['is_pax_added'])) {
            AddonBookingService::insertBookingAddonTransactions(
                $booking->id,
                null,
                'Invoiced',
                $request['pax_addons_data'] ?? null,
                $request['addons_data'] ?? null,
                'paid'
            );
        }

        // This below condition only execute when new pax added in the existing bookings
        if (isset($request['is_pax_added'])) {
            $history = json_decode($tourBookingHistory->details, true);

            $tourBookingHistory->ticket_cost = $history['amount'];
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = 'Invoiced';
            $tourBookingHistory->payment_method = $paymentMethod;
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }

}
