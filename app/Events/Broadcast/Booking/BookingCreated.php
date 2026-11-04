<?php

namespace App\Events\Broadcast\Booking;

use App\Events\Broadcast\BaseBroadcastEvent;
use App\Events\Broadcast\Concerns\EnrichesBookingRelations;

/**
 * Real-time notification that a new booking was created.
 * Replaces the notification aspect of the legacy BookingNew event.
 */
class BookingCreated extends BaseBroadcastEvent
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
    public function operation(): string  { return 'created'; }

    protected function channelIdentifier(): string { return $this->channel; }

    public function payload(): array
    {
        return $this->booking;
    }
}
