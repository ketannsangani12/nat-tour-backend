<?php

namespace App\Listeners\Broadcast;

use App\Events\BookingNew;
use App\Events\Broadcast\Calendar\SeatsUpdated;
use App\Events\Broadcast\Booking\BookingCreated;

/**
 * Listens to the legacy BookingNew event and dual-dispatches
 * equivalent events on the new tenant.* channel.
 *
 * Fires two new events per booking (matching the legacy dual-dispatch pattern):
 *   - Calendar\SeatsUpdated  — triggers a seat count refresh in the calendar UI
 *   - Booking\BookingCreated — triggers booking notification UI
 *
 * No changes to existing dispatch sites are needed.
 */
class BroadcastNewBookingToTenantChannel
{
    public function handle(BookingNew $event): void
    {
        $booking = $event->booking;
        $channel = $this->resolveChannel($booking);

        SeatsUpdated::dispatch($booking, 'created', $channel);
        BookingCreated::dispatch($booking, $channel);
    }

    /**
     * Mirror the channel resolution logic from BookingNew::broadcastOn().
     * When notif_for is 'Tour Operator', the channel suffix is the encrypted
     * tour operator ID; otherwise notif_for itself is the suffix (e.g. 'admin').
     */
    private function resolveChannel(array $booking): string
    {
        if (($booking['notif_for'] ?? '') === 'Tour Operator') {
            return $booking['tour_operator_id'];
        }

        return $booking['notif_for'] ?? 'admin';
    }
}
