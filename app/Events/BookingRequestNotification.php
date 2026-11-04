<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRequestNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingRequest;

    public $tenant;

    /**
     * Create a new event instance.
     */
    public function __construct($bookingRequest, $tenant)
    {
        $this->bookingRequest = $bookingRequest;
        $this->tenant = $tenant;
    }

    /**
     * Get the channels the event should broadcast on.
     *
	 * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('bookings-request');
    }

    public function broadcastAs()
    {
        return 'booking-request-notification';
    }

    public function broadcastWith()
    {
        return [
            'bookingRequest' => $this->bookingRequest,
            'tenant' => $this->tenant,
        ];
    }
}