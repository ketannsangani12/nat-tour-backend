<?php

namespace App\Listeners\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingEmailEvent;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use jdavidbakr\MailTracker\Events\ViewEmailEvent;

class EmailViewed
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
    public function handle(ViewEmailEvent $event)
    {
        $tracker = $event->sent_email;
        $bookingId = $tracker->getHeader('X-Model-ID');
        $emailType = $tracker->getHeader('X-Model-TP');
        $tenantId  = $tracker->getHeader('X-Model-TID');

        if ($bookingId && $emailType && tenant()->id == $tenantId) {
            $tourBooking = TourBooking::with('tourPackage.tourOperator')
                ->where('id', $bookingId)
                ->first();

            $sentEmailId = $tracker->id;
            $now         = Carbon::now()->format('Y-m-d H:i:s');

            if (($emailType == 1 || $emailType == 2 || $emailType == 10) && isset($tourBooking->tourPackage->tourOperator->name)) {
                $operationBy         = $tracker->getHeader('X-Model-OB');
                $operationByUserType = $tracker->getHeader('X-Model-UT');

                $emailEvent = TourBookingEmailEvent::where('tour_booking_id', $bookingId)
                    ->where('sent_email_id', $sentEmailId)
                    ->where('status', 'Open')
                    ->first();

                if ($emailEvent) {
                    $emailEvent->opens      = ($emailEvent->opens ?? 0) + 1;
                    $emailEvent->updated_at = $now;
                    $emailEvent->save();
                } else {
                    if ($emailType == 1) {
                        $details = $tourBooking->tourPackage->tourOperator->name . ' closure email viewed';
                    } elseif ($emailType == 10) {
                        $subject = $tracker->getHeader('X-Model-SUB');
                        $details = $tourBooking->tourPackage->tourOperator->name . '(Subject : ' . $subject . ') custom email viewed';
                    } else {
                        $details = $tourBooking->tourPackage->tourOperator->name . ' booking email viewed';
                    }

                    $tourBookingHistory = new TourBookingHistory();
                    $tourBookingHistory->tour_booking_id         = $bookingId;
                    $tourBookingHistory->operation               = 'Email';
                    $tourBookingHistory->details                 = $details;
                    $tourBookingHistory->operation_at            = Carbon::now();
                    $tourBookingHistory->operation_by            = $operationBy;
                    $tourBookingHistory->operation_by_user_type  = $operationByUserType;
                    $tourBookingHistory->save();

                    TourBookingEmailEvent::create([
                        'tour_booking_id'         => $bookingId,
                        'tour_booking_history_id' => $tourBookingHistory->id,
                        'sent_email_id'           => $sentEmailId,
                        'status'                  => 'Open',
                        'opens'                   => 1,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ]);
                }
            } elseif ($emailType == 11) {
                $tourBookings = TourBooking::with('tourPackage.tourOperator')
                    ->where('group_id', $bookingId)
                    ->get();
                $operationBy         = $tracker->getHeader('X-Model-OB');
                $operationByUserType = $tracker->getHeader('X-Model-UT');

                if ($tourBookings->isNotEmpty()) {
                    $existingEvents = TourBookingEmailEvent::whereIn('tour_booking_id', $tourBookings->pluck('id'))
                        ->where('sent_email_id', $sentEmailId)
                        ->where('status', 'Open')
                        ->get()
                        ->keyBy('tour_booking_id');

                    foreach ($tourBookings as $tourBooking) {
                        $emailEvent = $existingEvents->get($tourBooking->id);

                        if ($emailEvent) {
                            $emailEvent->opens      = ($emailEvent->opens ?? 0) + 1;
                            $emailEvent->updated_at = $now;
                            $emailEvent->save();
                        } else {
                            $tourBookingHistory = new TourBookingHistory();
                            $tourBookingHistory->tour_booking_id        = $tourBooking->id;
                            $tourBookingHistory->operation              = 'Email';
                            $tourBookingHistory->details                = $tourBooking->tourPackage->tourOperator->name . ' POS digital receipt email viewed';
                            $tourBookingHistory->operation_at           = Carbon::now();
                            $tourBookingHistory->operation_by           = $operationBy;
                            $tourBookingHistory->operation_by_user_type = $operationByUserType;
                            $tourBookingHistory->save();

                            TourBookingEmailEvent::create([
                                'tour_booking_id'         => $tourBooking->id,
                                'tour_booking_history_id' => $tourBookingHistory->id,
                                'sent_email_id'           => $sentEmailId,
                                'status'                  => 'Open',
                                'opens'                   => 1,
                                'created_at'              => $now,
                                'updated_at'              => $now,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
