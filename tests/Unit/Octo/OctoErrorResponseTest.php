<?php

namespace Tests\Unit\Octo;

use App\Helpers\OctoErrorResponse;
use Tests\TestCase;

/**
 * Unit tests for the OctoErrorResponse envelope builder.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoErrorResponseTest.php
 */
class OctoErrorResponseTest extends TestCase
{
    protected $tenancy = false;

    public function test_unauthorized_default_message(): void
    {
        $response = OctoErrorResponse::unauthorized();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame([
            'error'        => 'UNAUTHORIZED',
            'errorMessage' => 'API key not sent in Authorization header.',
        ], json_decode($response->getContent(), true));
    }

    public function test_unauthorized_custom_message(): void
    {
        $response = OctoErrorResponse::unauthorized('custom message');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('custom message', json_decode($response->getContent(), true)['errorMessage']);
    }

    public function test_forbidden(): void
    {
        $response = OctoErrorResponse::forbidden();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', json_decode($response->getContent(), true)['error']);
    }

    public function test_bad_request(): void
    {
        $response = OctoErrorResponse::badRequest();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('BAD_REQUEST', json_decode($response->getContent(), true)['error']);
    }

    public function test_unprocessable_entity_returns_http_400_per_octo_spec(): void
    {
        $response = OctoErrorResponse::unprocessableEntity();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('UNPROCESSABLE_ENTITY', json_decode($response->getContent(), true)['error']);
    }

    public function test_invalid_product_id_merges_context(): void
    {
        $response = OctoErrorResponse::invalidProductId('abc-123');

        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_PRODUCT_ID', $body['error']);
        $this->assertSame('abc-123', $body['productId']);
    }

    public function test_invalid_option_id_merges_context(): void
    {
        $response = OctoErrorResponse::invalidOptionId('opt-1');

        $body = json_decode($response->getContent(), true);
        $this->assertSame('INVALID_OPTION_ID', $body['error']);
        $this->assertSame('opt-1', $body['optionId']);
    }

    public function test_invalid_unit_id_merges_context(): void
    {
        $response = OctoErrorResponse::invalidUnitId('unit-1');

        $body = json_decode($response->getContent(), true);
        $this->assertSame('INVALID_UNIT_ID', $body['error']);
        $this->assertSame('unit-1', $body['unitId']);
    }

    public function test_invalid_availability_id_merges_context(): void
    {
        $response = OctoErrorResponse::invalidAvailabilityId('avail-1');

        $body = json_decode($response->getContent(), true);
        $this->assertSame('INVALID_AVAILABILITY_ID', $body['error']);
        $this->assertSame('avail-1', $body['availabilityId']);
    }

    public function test_invalid_booking_uuid_merges_context(): void
    {
        $response = OctoErrorResponse::invalidBookingUuid('uuid-1');

        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_BOOKING_UUID', $body['error']);
        $this->assertSame('uuid-1', $body['uuid']);
    }

    public function test_booking_expired(): void
    {
        $response = OctoErrorResponse::bookingExpired('uuid-2');

        $body = json_decode($response->getContent(), true);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('BOOKING_EXPIRED', $body['error']);
        $this->assertSame('uuid-2', $body['uuid']);
    }

    public function test_booking_already_cancelled(): void
    {
        $response = OctoErrorResponse::bookingAlreadyCancelled('uuid-3');

        $body = json_decode($response->getContent(), true);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('BOOKING_ALREADY_CANCELLED', $body['error']);
        $this->assertSame('uuid-3', $body['uuid']);
    }

    public function test_cancellation_not_allowed(): void
    {
        $response = OctoErrorResponse::cancellationNotAllowed();

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('CANCELLATION_NOT_ALLOWED', json_decode($response->getContent(), true)['error']);
    }

    public function test_internal_server_error(): void
    {
        $response = OctoErrorResponse::internalServerError();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('INTERNAL_SERVER_ERROR', json_decode($response->getContent(), true)['error']);
    }

    public function test_make_builds_envelope_with_arbitrary_context(): void
    {
        $response = OctoErrorResponse::make(422, 'CUSTOM_CODE', 'custom message', ['foo' => 'bar']);

        $body = json_decode($response->getContent(), true);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'error'        => 'CUSTOM_CODE',
            'errorMessage' => 'custom message',
            'foo'          => 'bar',
        ], $body);
    }
}
