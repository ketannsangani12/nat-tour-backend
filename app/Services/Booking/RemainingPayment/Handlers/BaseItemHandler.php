<?php

namespace App\Services\Booking\RemainingPayment\Handlers;

use App\Models\TourBookingPaymentLinkItem;

abstract class BaseItemHandler
{
    abstract public static function finalize(
        TourBookingPaymentLinkItem $item,
        int|string $bookingId,
        object $paymentIntent,
        string $paymentMethod = 'Card',
        ?int $transactionId = null
    ): void;

    /**
     * Return true if this item still needs to be processed (not already paid via
     * a separate Stripe charge or prior run). Called before creating the shared
     * transaction so only processable items contribute to the amount/fees.
     */
    abstract public static function isProcessable(TourBookingPaymentLinkItem $item): bool;

    /**
     * Return the fee portion for this item — used by RemainingPaymentService
     * to pre-compute total fees before creating a single shared transaction.
     */
    abstract public static function extractFees(TourBookingPaymentLinkItem $item): float;

    abstract public static function getLabel(): string;
}
