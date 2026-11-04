<?php

namespace App\Events\Broadcast\Booking;

use App\Events\Broadcast\BaseBroadcastEvent;
use App\Events\Broadcast\Concerns\EnrichesBookingRelations;

/**
 * Real-time notification for an affiliate/partner booking update.
 * Replaces the legacy BookingAffiliate event.
 */
class AffiliateBookingUpdated extends BaseBroadcastEvent
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
    public function operation(): string  { return 'affiliate-updated'; }

    protected function channelIdentifier(): string { return $this->channel; }

    public function payload(): array
    {
        return $this->booking;
    }
}
