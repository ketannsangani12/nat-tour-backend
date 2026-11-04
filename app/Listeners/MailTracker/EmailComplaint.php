<?php

namespace App\Listeners\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class EmailComplaint
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
    public function handle(ComplaintMessageEvent $event)
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
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' closure email goes to spam';
                } else {
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' booking email goes to spam';
                }

                $tourBookingHistory->operation_at = Carbon::now();
                $tourBookingHistory->operation_by = 1;
                $tourBookingHistory->operation_by_user_type = 'Admin';
                $tourBookingHistory->save();
            }
        }
    }
}
