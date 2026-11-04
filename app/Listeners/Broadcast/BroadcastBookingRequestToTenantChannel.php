<?php

namespace App\Listeners\Broadcast;

use App\Events\BookingRequestNotification;
use App\Events\Broadcast\Request\BookingRequestUpdated;

/**
 * Listens to the legacy BookingRequestNotification event and dual-dispatches
 * onto the new tenant.* channel.
 *
 * The booking request status is not stored on the legacy event, so the
 * operation defaults to 'updated'. Callers that need 'approved' / 'denied'
 * should dispatch BookingRequestUpdated directly once fully migrated.
 */
class BroadcastBookingRequestToTenantChannel
{
    public function handle(BookingRequestNotification $event): void
    {
        BookingRequestUpdated::dispatch(
            (array) $event->bookingRequest,
            (array) $event->tenant,
            'updated'
        );
    }
}
