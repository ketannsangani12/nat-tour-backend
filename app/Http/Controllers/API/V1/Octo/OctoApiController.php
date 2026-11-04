<?php

namespace App\Http\Controllers\API\V1\Octo;

use App\Helpers\OctoErrorResponse;
use App\Repositories\Octo\OctoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class OctoApiController
{
    protected OctoRepository $octoRepository;

    public function __construct(OctoRepository $octoRepository)
    {
        $this->octoRepository = $octoRepository;
    }

    /**
     * GET /supplier
     * Returns supplier details in OCTO format.
     */
    public function getSupplier(): JsonResponse
    {
        try {
            return $this->octoRepository->getSupplier();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /products
     * Returns the list of products
     */
    public function getProductList(): JsonResponse
    {
        try {
            return $this->octoRepository->getProductList();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /products/{productId}
     * Returns the details of a specific product by its ID
     */
    public function getProductById(string $productId): JsonResponse
    {
        try {
            return $this->octoRepository->getProductById($productId);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /availability/calendar
     * Returns date-level availability for a product within a date range
     */
    public function getAvailabilityCalendar(): JsonResponse
    {
        try {
            return $this->octoRepository->getAvailabilityCalendar();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /availability
     * Returns slot-level availability for a product within a date range
     */
    public function getAvailabilityCheck(): JsonResponse
    {
        try {
            return $this->octoRepository->getAvailabilityCheck();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /bookings
     * Lists bookings filtered by resellerReference or supplierReference
     */
    public function listBookings(): JsonResponse
    {
        try {
            return $this->octoRepository->listBookings();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings
     * Creates a new booking
     */
    public function createBooking(): JsonResponse
    {
        try {
            return $this->octoRepository->createBooking();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings/{uuid}/confirm
     * Confirms a booking by its UUID
     */
    public function confirmBooking(string $uuid): JsonResponse
    {
        try {
            return $this->octoRepository->confirmBooking($uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /bookings/{uuid}
     * Returns the details of a specific booking by its UUID
     */
    public function getBooking(string $uuid): JsonResponse
    {
        try {
            return $this->octoRepository->getBooking($uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * PATCH /bookings/{uuid}
     * Updates an ON_HOLD or CONFIRMED booking
     */
    public function updateBooking(string $uuid): JsonResponse
    {
        try {
            return $this->octoRepository->updateBooking($uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings/{uuid}/extend
     * Extends the expiration time of an ON_HOLD booking
     */
    public function extendBooking(string $uuid): JsonResponse
    {
        try {
            return $this->octoRepository->extendBooking($uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    // ── Webhooks (notifications capability) ───────────────────────────────────

    /**
     * GET /webhooks
     * Lists all webhooks
     */
    public function listWebhooks(): JsonResponse
    {
        try {
            return $this->octoRepository->listWebhooks();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /webhooks
     * Creates a new webhook
     */
    public function createWebhook(): JsonResponse
    {
        try {
            return $this->octoRepository->createWebhook();
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * GET /webhooks/{id}
     * Returns the details of a specific webhook by its ID
     */
    public function getWebhook(string $id): JsonResponse
    {
        try {
            return $this->octoRepository->getWebhook($id);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * PATCH /notifications/subscriptions/{id}
     * Updates a notification subscription (url, headers, events, active).
     */
    public function updateWebhook(string $id): JsonResponse
    {
        try {
            return $this->octoRepository->updateWebhook($id);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * DELETE /webhooks/{id}
     * Deletes a specific webhook by its ID
     */
    public function deleteWebhook(string $id): JsonResponse
    {
        try {
            return $this->octoRepository->deleteWebhook($id);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /webhooks/{id}/ping
     * Pings a specific webhook by its ID
     */
    public function pingWebhook(string $id): JsonResponse
    {
        try {
            return $this->octoRepository->pingWebhook($id);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    /**
     * POST /bookings/{uuid}/cancel
     * Cancels a booking by its UUID
     */
    public function cancelBooking(string $uuid): JsonResponse
    {
        try {
            return $this->octoRepository->cancelBooking($uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }
}
