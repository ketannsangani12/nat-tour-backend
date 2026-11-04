<?php

namespace App\Listeners\Broadcast;

use App\Events\BookingAffiliate;
use App\Events\Broadcast\Calendar\SeatsUpdated;
use App\Events\Broadcast\Booking\AffiliateBookingUpdated;

/**
 * Listens to the legacy BookingAffiliate event and dual-dispatches
 * equivalent events on the new tenant.* channel.
 */
class BroadcastAffiliateBookingToTenantChannel
{
    public function handle(BookingAffiliate $event): void
    {
        $booking = $event->booking;
        $channel = $this->resolveChannel($booking);

        SeatsUpdated::dispatch($booking, 'affiliate-updated', $channel);
        AffiliateBookingUpdated::dispatch($booking, $channel);
    }

    private function resolveChannel(array $booking): string
    {
        if (($booking['notif_for'] ?? '') === 'Tour Operator') {
            return $booking['tour_operator_id'];
        }

        return $booking['notif_for'] ?? 'admin';
    }
}
