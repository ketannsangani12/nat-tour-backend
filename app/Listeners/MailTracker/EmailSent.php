<?php

namespace App\Listeners\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingEmailEvent;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use jdavidbakr\MailTracker\Events\EmailSentEvent;

class EmailSent
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
    public function handle(EmailSentEvent $event)
    {
        $tracker = $event->sent_email;
        $bookingId = $tracker->getHeader('X-Model-ID');
        $emailType = $tracker->getHeader('X-Model-TP');
        $tenantId = $tracker->getHeader('X-Model-TID');

        if ($bookingId && $emailType && tenant()->id == $tenantId) {
            $tourBooking = TourBooking::with('tourPackage.tourOperator')
                ->where('id', $bookingId)
                ->first();

            $sentEmailId = $tracker->id;
            $sentAt = ($tracker->created_at ?? Carbon::now())->format('Y-m-d H:i:s');

            if (($emailType == 1 || $emailType == 2 || $emailType == 10 || $emailType == 4) && isset($tourBooking->tourPackage->tourOperator->name)) {
                $operationBy = $tracker->getHeader('X-Model-OB');
                $operationByUserType = $tracker->getHeader('X-Model-UT');
                $tourBookingHistory = new TourBookingHistory();
                $tourBookingHistory->tour_booking_id = $bookingId;
                $tourBookingHistory->operation = 'Email';

                if ($emailType == 1) {
                    $message = $tourBooking->tourPackage->tourOperator->name . ' closure email sent';
                } else if ($emailType == 10) {
                    $subject = $tracker->getHeader('X-Model-SUB');
                    $message = $tourBooking->tourPackage->tourOperator->name . '(Subject : ' . $subject . ') custom email sent';
                } else if ($emailType == 4) {
                    $message = $tourBooking->tourPackage->tourOperator->name . ' rescheduled email sent';
                } else {
                    $message = $tourBooking->tourPackage->tourOperator->name . ' booking email sent';
                }

                $tourBookingHistory->details = $message;
                $tourBookingHistory->operation_at = Carbon::now();
                $tourBookingHistory->operation_by = $operationBy;
                $tourBookingHistory->operation_by_user_type = $operationByUserType;
                $tourBookingHistory->save();

                $exists = TourBookingEmailEvent::where('tour_booking_id', $bookingId)
                    ->where('tour_booking_history_id', $tourBookingHistory->id)
                    ->where('sent_email_id', $sentEmailId)
                    ->where('status', 'Sent')
                    ->exists();

                if (!$exists) {
                    TourBookingEmailEvent::create([
                        'tour_booking_id' => $bookingId,
                        'tour_booking_history_id' => $tourBookingHistory->id,
                        'sent_email_id' => $sentEmailId,
                        'status' => 'Sent',
                        'created_at' => $sentAt,
                        'updated_at' => $sentAt,
                    ]);
                }
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
                        $tourBookingHistory->details = $tourBooking->tourPackage->tourOperator->name . ' POS digital receipt email sent';
                        $tourBookingHistory->operation_at = Carbon::now();
                        $tourBookingHistory->operation_by = $operationBy;
                        $tourBookingHistory->operation_by_user_type = $operationByUserType;
                        $tourBookingHistory->save();

                        $exists = TourBookingEmailEvent::where('tour_booking_id', $tourBooking->id)
                            ->where('tour_booking_history_id', $tourBookingHistory->id)
                            ->where('sent_email_id', $sentEmailId)
                            ->where('status', 'Sent')
                            ->exists();

                        if (!$exists) {
                            TourBookingEmailEvent::create([
                                'tour_booking_id' => $tourBooking->id,
                                'tour_booking_history_id' => $tourBookingHistory->id,
                                'sent_email_id' => $sentEmailId,
                                'status' => 'Sent',
                                'created_at' => $sentAt,
                                'updated_at' => $sentAt,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
