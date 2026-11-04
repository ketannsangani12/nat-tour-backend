<?php

namespace App\Listeners\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;

class EmailDelivered
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * Perform your tracking/linking tasks on $invoice knowing the SentEmail object
     *
     * @param  object  $event
     * @return void
     */
    public function handle(EmailDeliveredEvent $event)
    {
        $tracker = $event->sent_email;
        $bookingId = $tracker->getHeader('X-Model-ID');
        $emailType = $tracker->getHeader('X-Model-TP');
        $tenantId = $tracker->getHeader('X-Model-TID');

        if ($bookingId && $emailType && tenant()->id == $tenantId) {
            $tourBooking = TourBooking::with('tourPackage.tourOperator')
                ->where('id', $bookingId)
                ->first();

            if ($emailType == 1 || $emailType == 2) {
                $tourBookingHistory = new TourBookingHistory();
                $tourBookingHistory->tour_booking_id = $bookingId;
                $tourBookingHistory->operation = 'Email';

                if ($emailType == 1) {
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' closure email delivered';
                } else if ($emailType == 10) {
                    $subject = $tracker->getHeader('X-Model-SUB');
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . '(Subject : ' . $subject . ') custom email delivered';
                } else {
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' booking email delivered';
                }

                $tourBookingHistory->operation_at = Carbon::now();
                $tourBookingHistory->operation_by = 1;
                $tourBookingHistory->operation_by_user_type = 'Admin';
                $tourBookingHistory->save();
            } else if ($emailType == 11) {
                $tourBookings = TourBooking::with('tourPackage.tourOperator')
                    ->where('group_id', $bookingId)
                    ->get();
                $operationBy = $tracker->getHeader('X-Model-OB');
                $operationByUserType = $tracker->getHeader('X-Model-UT');
                if ($tourBookings) {
                    foreach ($tourBookings as $tourBooking) {
                        $tourBookingHistory = new TourBookingHistory();
                        $tourBookingHistory->tour_booking_id = $tourBooking->id;
                        $tourBookingHistory->operation = 'Email';
                        $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' POS digital receipt email delivered';
                        $tourBookingHistory->operation_at = Carbon::now();
                        $tourBookingHistory->operation_by = $operationBy;
                        $tourBookingHistory->operation_by_user_type = $operationByUserType;
                        $tourBookingHistory->save();
                    }
                }
            }
        }
    }
}
