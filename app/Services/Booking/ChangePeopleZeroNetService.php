<?php

namespace App\Services\Booking;

use App\Services\Board\BoardService;
use App\Services\Booking\HistoryService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Handles the Change People path where the net charge/refund is exactly $0.
 *
 * This occurs when an unpaid-pax correction brings $total to zero (e.g. removing
 * 1 unpaid Guest and adding 1 free Local). In this case no Stripe or Payment Link
 * action is needed — the booking_total / pax counts are already written by the
 * calling repository method; this service writes the pax-change history entry,
 * updates the slot summary, fires the board refresh, and commits.
 */
class ChangePeopleZeroNetService
{
    /**
     * @param  \App\Models\TourBooking  $tourBooking
     * @param  array   $historyData         Pax-change summary (tourists, added/deleted texts, etc.)
     * @param  int     $addedPeopleCount     Net added tourists (infants already excluded)
     * @param  int     $deletedPeopleCount   Net deleted tourists (infants already excluded)
     * @param  mixed   $slotTimeId           tour_slot_time_id for slot summary update
     * @param  string  $createdByWithName
     * @param  array   $errors
     * @return array   Response array compatible with changePeopleBooking's return shape
     */
    public static function commit(
        $tourBooking,
        array $historyData,
        int $addedPeopleCount,
        int $deletedPeopleCount,
        $slotTimeId,
        string $createdByWithName,
        array $errors = []
    ): array {
        HistoryService::changeTouristInHistory($tourBooking->id, $historyData, $createdByWithName);

        $seatsDiff = $addedPeopleCount - $deletedPeopleCount;
        TimeSlotAvailabilityService::updateSlotSummary($slotTimeId, $seatsDiff);

        BoardService::boardBookingUpdate($tourBooking->id);

        DB::commit();

        return [
            'bookingId'     => $tourBooking->id,
            'bookingToken'  => Crypt::encryptString($tourBooking->id),
            'stripeId'      => '',
            'transactionId' => Crypt::encryptString(''),
            'errors'        => $errors,
        ];
    }

    /**
     * Returns true when the combined net amount (tour + add-ons) is exactly zero,
     * meaning no Stripe or Payment Link action is needed.
     */
    public static function applies(float $tt, float $addonNetPrice, float $addonNetFee): bool
    {
        return $tt == 0 && ($addonNetPrice + $addonNetFee) == 0;
    }
}
