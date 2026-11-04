<?php

namespace App\Listeners\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;

class BouncedEmail
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
    public function handle(PermanentBouncedMessageEvent $event)
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
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' closure email bounced';
                }else if ($emailType == 10) {
					$subject = $tracker->getHeader('X-Model-SUB');
					$tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name .'(Subject : '.$subject.') custom email bounced';
				} else {
                    $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' booking email bounced';
                }

                $tourBookingHistory->operation_at = Carbon::now();
                $tourBookingHistory->operation_by = 1;
                $tourBookingHistory->operation_by_user_type = 'Admin';
                $tourBookingHistory->save();
            } else if ($emailType == 11) {
                $tourBookings = TourBooking::with('tourPackage.tourOperator')
                    ->where('group_id', $bookingId)
                    ->get();
                if ($tourBookings) {
                    foreach ($tourBookings as $tourBooking) {
                        $tourBookingHistory = new TourBookingHistory();
                        $tourBookingHistory->tour_booking_id = $tourBooking->id;
                        $tourBookingHistory->operation = 'Email';
                        $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' POS digital receipt email bounced';
                        $tourBookingHistory->operation_at = Carbon::now();
                        $tourBookingHistory->operation_by = 1;
                        $tourBookingHistory->operation_by_user_type = 'Admin';
                        $tourBookingHistory->save();
                    }
                }
            }
        }
    }
}
