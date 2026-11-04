<?php

namespace Tests\Unit\Bookings;

use App\Models\Customer;
use App\Models\SaveCreditCard;
use App\Services\Booking\HistoryService;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: a real crash on tenant `dixies` — "ErrorException: Attempt to read property
 * 'email' on null in HistoryService.php:2381" — via the Payment Link success flow
 * (PaymentLinkService::savePaymentUpdateBooking() -> HistoryService::getSavedCardFromPaymentIntent()
 * -> saveCreditCardDetails()). Customer::find($tourBooking->customer_id) is scoped by the Customer
 * model's SoftDeletes trait, so a customer row that's been (soft-)deleted since the booking was
 * created returns null even though $tourBooking->customer_id is a real, valid id -- and the method
 * unconditionally read $customer->email to build the save_credit_card row.
 */
class SaveCreditCardDetailsMissingCustomerTest extends TestCase
{
    public function test_falls_back_to_booking_email_when_the_customer_row_is_unavailable(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $customerId = $booking->customerId;

        // Simulate the real-world drift: the customer row is gone from Customer::find()'s
        // perspective (soft-deleted), even though the booking's own customer_id still points at
        // a real row and the booking's own email column is untouched.
        Customer::find($customerId)->delete();

        try {
            $result = HistoryService::saveCreditCardDetails(
                $bookingId,
                'cus_test_stripe_customer_id',
                'pm_test_payment_method',
                'visa',
                '4242',
                12,
                2039,
                'PAYMENT LINK'
            );

            $this->assertNotNull($result, 'saveCreditCardDetails() must not crash when the customer row is unavailable');

            $saved = SaveCreditCard::where('tour_booking_id', $bookingId)->first();
            $this->assertNotNull($saved);
            $this->assertNotEmpty(
                $saved->email,
                'Must fall back to the booking\'s own email column rather than leaving/crashing on a null customer email'
            );
        } finally {
            SaveCreditCard::where('tour_booking_id', $bookingId)->delete();
            // cleanup() deletes the booking rows before the customer row (FK order), then hard-
            // deletes the customer via a raw query that bypasses the SoftDeletes scope, so the
            // earlier soft-delete above doesn't need a separate forceDelete() here.
            $booking->cleanup();
        }
    }
}
