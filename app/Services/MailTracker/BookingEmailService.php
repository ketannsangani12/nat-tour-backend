<?php

namespace App\Services\MailTracker;

use App\Models\TourBooking;
use App\Models\TourBookingEmailEvent;
use App\Models\TourBookingHistory;
use App\Models\TourOperator;
use App\Models\TourPackage;
use Carbon\Carbon;
use jdavidbakr\MailTracker\Model\SentEmail;

class BookingEmailService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($id, $type, $messageId = null, $reason = null)
    {
        $booking = TourBooking::find($id);

        if ($booking) {
            $package  = TourPackage::find($booking->tour_package_id);
            $operator = TourOperator::find($package->tour_operator_id);

            $sentEmailQuery = SentEmail::query()->where('tour_booking_id', $id);
            if ($messageId) {
                $sentEmailQuery->where('message_id', $messageId);
            }
            $sentEmail = $sentEmailQuery->latest('id')->first();

            $date = Carbon::now()->format('Y-m-d H:i:s');

            $statusMap = [
                'bounce'    => 'Bounce',
                'complaint' => 'Complaint',
                'delivery'  => 'Delivered',
            ];

            switch ($type) {
                case 'bounce':
                    $details = $operator->name . ' booking email has bounce';
                    break;
                case 'complaint':
                    $details = $operator->name . ' booking email sent in a spam complaint';
                    break;
                case 'delivery':
                    $details = $operator->name . ' booking email delivered successfully';
                    break;
                default:
                    return;
            }

            $history = static::logEmailEntry($id, $details);
            $status  = $statusMap[$type];

            $exists = TourBookingEmailEvent::where('tour_booking_id', $id)
                ->where('tour_booking_history_id', $history->id)
                ->where('status', $status)
                ->exists();

            if (!$exists) {
                TourBookingEmailEvent::create([
                    'tour_booking_id'         => $id,
                    'tour_booking_history_id' => $history->id,
                    'sent_email_id'           => $sentEmail?->id,
                    'status'                  => $status,
                    'reason'                  => $reason,
                    'created_at'              => $date,
                    'updated_at'              => $date,
                ]);
            }
        }
    }

    private static function logEmailEntry($id, $details)
    {
        $tourBookingHistory = new TourBookingHistory();
        $tourBookingHistory->tour_booking_id        = $id;
        $tourBookingHistory->operation              = 'Email';
        $tourBookingHistory->details                = $details;
        $tourBookingHistory->operation_at           = Carbon::now();
        $tourBookingHistory->operation_by           = 1;
        $tourBookingHistory->operation_by_user_type = 'Admin';
        $tourBookingHistory->save();

        return $tourBookingHistory;
    }
}
