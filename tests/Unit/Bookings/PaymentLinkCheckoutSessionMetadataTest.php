<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: every OTHER Stripe-charging flow (Make Payment, New Booking, Change People, ...)
 * sets a human-readable `description` plus a structured `metadata.bookings` payload directly on
 * the PaymentIntent it creates (see StripeUtils::createStripeIntent()). PaymentLinkService::
 * createPaymentLink() — the customer-facing page that actually charges the card via a Stripe
 * Checkout Session — never did: it only set metadata on the Checkout SESSION (used by
 * savePaymentUpdateBooking()'s own reconciliation), which Stripe does NOT copy onto the
 * PaymentIntent it creates under the hood. Every Payment Link payment showed up in Stripe with a
 * blank description and no booking metadata at all.
 *
 * CMD: php artisan test --filter=PaymentLinkCheckoutSessionMetadataTest
 */
class PaymentLinkCheckoutSessionMetadataTest extends TestCase
{
    public function test_checkout_session_sets_description_and_metadata_on_the_payment_intent(): void
    {
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->withCustomer('Jane Doe', 'checkout-session-meta-' . uniqid() . '@test.com', '5550001111')
            ->create();
        $bookingId = $booking->bookingId;
        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'                => 100.00,
            'reason_of_payment'    => 'Make Payment',
            'email'                => $booking->customerEmail ?? 'checkout-session-meta@test.com',
            'phone_number'         => '5550001111',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $capturedSessionParams = null;

        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('listCustomers')->willReturn((object) ['data' => []]);
        $fakeGateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test_checkout_session']);
        $fakeGateway->method('buildConnectedPaymentData')->willReturn([
            'application_fee_amount' => 500,
            'transfer_data'          => ['destination' => 'acct_test'],
        ]);
        $fakeGateway->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturnCallback(function ($params) use (&$capturedSessionParams) {
                $capturedSessionParams = $params;
                return (object) ['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/test'];
            });
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            PaymentLinkService::createPaymentLink($link->token);

            $this->assertNotNull($capturedSessionParams, 'createCheckoutSession must have been called');
            $this->assertArrayHasKey('payment_intent_data', $capturedSessionParams);
            $piData = $capturedSessionParams['payment_intent_data'];

            $this->assertArrayHasKey('description', $piData, 'The PaymentIntent must carry a description, same as every other Stripe-charging flow');
            $this->assertStringContainsString('Booking Id: ' . $bookingId, $piData['description']);
            $this->assertStringContainsString('Tourists: 2', $piData['description']);

            $this->assertArrayHasKey('metadata', $piData, 'The PaymentIntent must carry metadata, same as every other Stripe-charging flow');
            $this->assertArrayHasKey('bookings', $piData['metadata']);
            $this->assertArrayHasKey('tenant_id', $piData['metadata']);

            $bookingsMeta = json_decode($piData['metadata']['bookings'], true);
            $this->assertEquals($bookingId, $bookingsMeta['booking_id']);
            $this->assertEquals(100.00, (float) $bookingsMeta['amount']);
            $this->assertEquals(2, $bookingsMeta['tourists']);
        } finally {
            TourBookingPaymentLink::where('id', $link->id)->delete();
            $booking->cleanup();
        }
    }
}
