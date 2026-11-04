<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * Generates OCTO-spec compliant error responses.
 *
 * Spec fields:  error (code), errorMessage (human-readable), + optional context field.
 * Reference:    https://docs.octo.travel
 */
class OctoErrorResponse
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public static function unauthorized(string $message = 'API key not sent in Authorization header.'): JsonResponse
    {
        return self::make(401, 'UNAUTHORIZED', $message);
    }

    public static function forbidden(string $message = 'Invalid or revoked API key.'): JsonResponse
    {
        return self::make(403, 'FORBIDDEN', $message);
    }

    // ── Client errors ─────────────────────────────────────────────────────────

    public static function badRequest(string $message = 'Request body not formatted correctly or missing required fields.'): JsonResponse
    {
        return self::make(400, 'BAD_REQUEST', $message);
    }

    public static function unprocessableEntity(string $message = 'Request is valid but cannot be processed.'): JsonResponse
    {
        return self::make(400, 'UNPROCESSABLE_ENTITY', $message);
    }

    public static function invalidProductId(string $productId = '', string $message = 'The Product ID was invalid or missing.'): JsonResponse
    {
        return self::make(400, 'INVALID_PRODUCT_ID', $message, ['productId' => $productId]);
    }

    public static function invalidOptionId(string $optionId = '', string $message = 'The Option ID was invalid or missing.'): JsonResponse
    {
        return self::make(400, 'INVALID_OPTION_ID', $message, ['optionId' => $optionId]);
    }

    public static function invalidUnitId(string $unitId = '', string $message = 'The Unit ID was invalid or missing.'): JsonResponse
    {
        return self::make(400, 'INVALID_UNIT_ID', $message, ['unitId' => $unitId]);
    }

    public static function invalidAvailabilityId(string $availabilityId = '', string $message = 'The Availability ID was invalid or missing.'): JsonResponse
    {
        return self::make(400, 'INVALID_AVAILABILITY_ID', $message, ['availabilityId' => $availabilityId]);
    }

    public static function invalidBookingUuid(string $uuid = '', string $message = 'The booking UUID was not found.'): JsonResponse
    {
        return self::make(400, 'INVALID_BOOKING_UUID', $message, ['uuid' => $uuid]);
    }

    /**
     * A client-supplied booking uuid was replayed for a different booking — either it
     * already belongs to another reseller, or it was reused with different booking
     * details. Reported as a conflict rather than silently resolving to the original,
     * so the reseller can see that its idempotency key is not describing what it thinks.
     */
    public static function bookingUuidConflict(string $uuid = '', string $message = 'The supplied booking uuid is already in use.'): JsonResponse
    {
        return self::make(409, 'BOOKING_UUID_CONFLICT', $message, ['uuid' => $uuid]);
    }

    public static function bookingExpired(string $uuid = ''): JsonResponse
    {
        return self::make(409, 'BOOKING_EXPIRED', 'The booking hold has expired and is no longer valid.', ['uuid' => $uuid]);
    }

    public static function bookingAlreadyCancelled(string $uuid = ''): JsonResponse
    {
        return self::make(409, 'BOOKING_ALREADY_CANCELLED', 'This booking has already been cancelled.', ['uuid' => $uuid]);
    }

    public static function cancellationNotAllowed(string $message = 'Cancellation is not allowed for this booking.'): JsonResponse
    {
        return self::make(409, 'CANCELLATION_NOT_ALLOWED', $message);
    }

    // ── Server errors ─────────────────────────────────────────────────────────

    public static function internalServerError(string $message = 'An unexpected server error occurred.'): JsonResponse
    {
        return self::make(500, 'INTERNAL_SERVER_ERROR', $message);
    }

    // ── Builder ───────────────────────────────────────────────────────────────

    /**
     * Build the OCTO error response envelope.
     *
     * @param  array<string, mixed>  $context  Optional extra fields (e.g. productId, optionId)
     */
    public static function make(int $status, string $errorCode, string $errorMessage, array $context = []): JsonResponse
    {
        $body = array_merge(
            [
                'error'        => $errorCode,
                'errorMessage' => $errorMessage,
            ],
            $context
        );

        return response()->json($body, $status);
    }
}
