<?php

namespace App\Events\Broadcast\Board;

use App\Events\Broadcast\BaseBroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Fired whenever a guide management action changes board state.
 * Covers: assign, unassign, auto-assign, bulk-assign, manual-update, check-in, swap, capacity update.
 *
 * Channel:    tenant.{channel}  (admin | tour_operator_id)
 * Event name: board.guide-management.updated
 * Payload:    { date, time_from }
 *
 * Implements ShouldBroadcastNow to bypass the queue and send to Pusher synchronously,
 * ensuring real-time delivery regardless of queue worker status.
 */
class BoardUpdated extends BaseBroadcastEvent implements ShouldBroadcastNow
{
    private string $channel;
    private string $date;
    private string $timeFrom;

    public function __construct(string $date, string $timeFrom, string $channel)
    {
        $this->date     = $date;
        $this->timeFrom = $timeFrom;
        $this->channel  = $channel;
    }

    public function feature(): string   { return 'board'; }
    public function entity(): string    { return 'guide-management'; }
    public function operation(): string { return 'updated'; }

    protected function channelIdentifier(): string { return $this->channel; }

    public function payload(): array
    {
        return [
            'date'      => $this->date,
            'time_from' => $this->timeFrom,
        ];
    }
}
