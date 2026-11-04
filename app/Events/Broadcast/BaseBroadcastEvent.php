<?php

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all real-time broadcast events.
 *
 * Channel contract:   tenant.{identifier}
 * Event name format:  {feature}.{entity}.{operation}
 * Payload contract:   { feature, entity, operation, payload, timestamp }
 *
 * Concrete events must implement:
 *   - feature()           e.g. 'calendar', 'booking', 'request'
 *   - entity()            e.g. 'seats', 'booking', 'request'
 *   - operation()         e.g. 'created', 'updated', 'cancelled'
 *   - payload()           the data the frontend needs
 *   - channelIdentifier() the segment after 'tenant.' (encrypted tour op ID or 'admin')
 */
abstract class BaseBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    abstract public function feature(): string;

    abstract public function entity(): string;

    abstract public function operation(): string;

    abstract public function payload(): array;

    abstract protected function channelIdentifier(): string;

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.' . $this->channelIdentifier());
    }

    public function broadcastAs(): string
    {
        return $this->feature() . '.' . $this->entity() . '.' . $this->operation();
    }

    public function broadcastWith(): array
    {
        return [
            'feature'   => $this->feature(),
            'entity'    => $this->entity(),
            'operation' => $this->operation(),
            'payload'   => $this->payload(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
