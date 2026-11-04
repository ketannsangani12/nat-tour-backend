<?php

namespace App\Events\Broadcast\Booking;

use App\Events\Broadcast\BaseBroadcastEvent;
use App\Events\Broadcast\Concerns\EnrichesBookingRelations;

/**
 * Real-time notification that a booking was cancelled.
 * Replaces the notification aspect of the legacy BookingCancelled event.
 */
class BookingCancelled extends BaseBroadcastEvent
{
    use EnrichesBookingRelations;

    private array $booking;
    private string $channel;

    public function __construct(array $booking, string $channel)
    {
        $this->booking = $this->enrichBookingRelations($booking);
        $this->channel = $channel;
    }

    public function feature(): string    { return 'booking'; }
    public function entity(): string     { return 'booking'; }
    public function operation(): string  { return 'cancelled'; }

    protected function channelIdentifier(): string { return $this->channel; }

    public function payload(): array
    {
        return $this->booking;
    }
}
