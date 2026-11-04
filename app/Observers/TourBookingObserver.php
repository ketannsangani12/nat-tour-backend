<?php

namespace App\Observers;

use App\Models\OctoBooking;
use App\Models\TourBooking;
use App\Constants\Constants;
use App\Repositories\Octo\OctoRepository;
use App\Services\TimeSlot\TimeSlotAvailabilityService;

class TourBookingObserver
{
    /**
     * Handle the TourBooking "created" event.
     */
    public function created(TourBooking $tourBooking): void
    {
        $tourBooking->group_id = null;

        $checkGroupId = TourBooking::where('group_id', Constants::GROUP_ID)->first();
        $getLastGroupId = TourBooking::select('group_id')->whereNotNull('group_id')->latest('group_id')->first();
        $comboBooking = null;
        if ($tourBooking->cart_id) {
            $comboBooking = TourBooking::select('group_id')
                ->where('cart_id', $tourBooking->cart_id)
                ->whereNotNull('group_id')
                ->latest('group_id')
                ->first();
        }

        if (!$checkGroupId || ($getLastGroupId == null)) {
            $tourBooking->group_id = Constants::GROUP_ID;
        }

        if ($comboBooking != null && $comboBooking->group_id != null) {
            $tourBooking->group_id = $comboBooking->group_id;
        } else {
            $lastId = ($getLastGroupId == null) ? $tourBooking->group_id : $getLastGroupId->group_id;
            if ($getLastGroupId != null) {
                $lastId++;
            }
            $tourBooking->group_id = $lastId;
        }

        $tourBooking->save();

        // TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);
    }

    /**
     * Handle the TourBooking "updated" event.
     */
    public function updated(TourBooking $tourBooking): void
    {
        // When a booking is cancelled from the operator's admin panel, sync the status
        // to the linked OCTO booking so the reseller sees the cancellation on their next
        // GET /bookings/{uuid} call (or via webhook once notifications are implemented).
        if ($tourBooking->wasChanged('status') && $tourBooking->status === 'Cancelled') {
            $octoBookings = OctoBooking::where('tour_booking_id', $tourBooking->id)
                ->where('status', 'CONFIRMED')
                ->get();

            if ($octoBookings->isNotEmpty()) {
                OctoBooking::whereIn('id', $octoBookings->pluck('id'))
                    ->update(['status' => 'CANCELLED', 'updated_at' => now()]);

                // Push webhook notifications to the reseller for each affected booking
                $repo = app(OctoRepository::class);
                foreach ($octoBookings as $octoBooking) {
                    $octoBooking->status = 'CANCELLED';
                    $repo->dispatchBookingNotifications($octoBooking);
                }
            }
        }
    }

    /**
     * Handle the TourBooking "deleted" event.
     */
    public function deleted(TourBooking $tourBooking): void
    {
        // TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, -$tourBooking->tourists);
    }

    /**
     * Handle the TourBooking "restored" event.
     */
    public function restored(TourBooking $tourBooking): void
    {
        //
    }

    /**
     * Handle the TourBooking "force deleted" event.
     */
    public function forceDeleted(TourBooking $tourBooking): void
    {
        //
    }
}
