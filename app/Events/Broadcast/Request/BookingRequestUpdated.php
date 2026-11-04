<?php

namespace App\Events\Broadcast\Request;

use App\Events\Broadcast\BaseBroadcastEvent;

/**
 * Real-time notification for a booking request approval or denial.
 * Replaces the legacy BookingRequestNotification event.
 */
class BookingRequestUpdated extends BaseBroadcastEvent
{
    private array $bookingRequest;
    private array $tenant;
    private string $op;

    /**
     * @param array  $bookingRequest
     * @param array  $tenant
     * @param string $operation      'approved' | 'denied'
     */
    public function __construct(array $bookingRequest, array $tenant, string $operation)
    {
        $this->bookingRequest = $bookingRequest;
        $this->tenant         = $tenant;
        $this->op             = $operation;
    }

    public function feature(): string    { return 'request'; }
    public function entity(): string     { return 'booking-request'; }
    public function operation(): string  { return $this->op; }

    protected function channelIdentifier(): string { return 'admin'; }

    public function payload(): array
    {
        return [
            'booking_request' => $this->bookingRequest,
            'tenant'          => $this->tenant,
        ];
    }
}
