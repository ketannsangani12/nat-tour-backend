<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\BookingTransact;

class TripAdvisorBookingService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $transactionId, $historyId)
    {
        $tourBookingHistory = TourBookingHistory::with('tourBooking.customer')
            ->where('id', $historyId)
            ->first();

        $tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

        $ticketCostTotal = 0;
        $permitFeesTotal = 0;
        $platformFeesTotal = 0;
        foreach ($tourBookingTransact as $pax) {
            $ticketCost = $pax->tourists * ($pax->rate + $pax->additional_charge + $pax->permit_fee);
            $ticketCostTotal += $ticketCost;

            $permitFee = $pax->tourists * $pax->permit_fee;
            $permitFeesTotal += $permitFee;
        }

        $tourBookingHistory->ticket_cost = $ticketCostTotal;
        $tourBookingHistory->permit_fees = $permitFeesTotal;
        $tourBookingHistory->platform_fees = $platformFeesTotal;
        $tourBookingHistory->transaction_id = $transactionId;
        $tourBookingHistory->payment_type = 'TripAdvisor';
        $tourBookingHistory->payment_method = 'Full Payment';
        $tourBookingHistory->is_processed = 1;
        $tourBookingHistory->save();
    }
}
