<?php

namespace App\Events\Broadcast\Calendar;

use App\Events\Broadcast\BaseBroadcastEvent;
use App\Events\Broadcast\Concerns\EnrichesBookingRelations;

/**
 * Fired whenever a booking action changes the seat count visible in the calendar.
 * Covers: new booking, cancellation, affiliate booking.
 *
 * Frontend handler: CalendarSocketModule → RealTimeUpdates
 */
class SeatsUpdated extends BaseBroadcastEvent
{
    use EnrichesBookingRelations;

    private array $booking;
    private string $op;
    private string $channel;

    /**
     * @param array  $booking   Booking data array (same shape passed to legacy events)
     * @param string $operation 'created' | 'cancelled' | 'affiliate'
     * @param string $channel   Encrypted tour operator ID or 'admin'
     */
    public function __construct(array $booking, string $operation, string $channel)
    {
        $this->booking = $this->enrichBookingRelations($booking);
        $this->op      = $operation;
        $this->channel = $channel;
    }

    public function feature(): string    { return 'calendar'; }
    public function entity(): string     { return 'seats'; }
    public function operation(): string  { return $this->op; }

    protected function channelIdentifier(): string { return $this->channel; }

    public function payload(): array
    {
        return [
            'pkg_id'             => $this->booking['pkg_id'] ?? null,
            'date'               => $this->booking['date'] ?? null,
            // tour_d_t is required by RealTimeUpdates.handleSimplePackageUpdate()
            // to locate the calendar cell (#cal-date-YYYY-MM-DD).
            'tour_d_t'           => $this->booking['tour_d_t'] ?? $this->booking['date'] ?? null,
            // tourists drives the immediate DOM delta update in handleSimplePackageUpdate().
            // For affiliate events this is seats_diff; for new bookings it is the tourist count.
            'tourists'           => $this->booking['tourists'] ?? $this->booking['seats_diff'] ?? null,
            'seats_diff'         => $this->booking['seats_diff'] ?? null,
            'has_relations'      => $this->booking['has_relations'],
            'is_parent'          => $this->booking['is_parent'],
            'is_child'           => $this->booking['is_child'],
            'parent_package_id'  => $this->booking['parent_package_id'] ?? null,
            'group_index'        => $this->booking['group_index'] ?? null,
            'related_package_ids' => $this->booking['related_package_ids'] ?? [],
        ];
    }
}
