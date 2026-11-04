<?php

namespace Tests\Unit\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\PaymentLinkService;
use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: PaymentLinkService::createPaymentLink() rebuilds its Stripe Checkout Session
 * fresh on every visit, but always pinned settlement mode to PaymentModeResolver::DESTINATION --
 * both in the buildConnectedPaymentData() call (no mode argument passed at all, defaulting to
 * DESTINATION) and in stripOnBehalfOfForCountry() (hardcoded DESTINATION). This meant a payment
 * link for a 2027+ booking -- including a "remaining_payment" link used to charge an add-on --
 * never actually settled via Payout Simplification; it always used today's destination-charge
 * structure regardless of tour date or the tenant's kill switch. Fixed by resolving the real
 * settlement mode from every booking sharing the link's order/group (the mixed-cart rule) fresh
 * on each rebuild, and threading it into both call sites.
 */
class PaymentLinkPlatformHeldModeTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => 0]);
        parent::tearDown();
    }

    private function setKillSwitch(bool $enabled): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => $enabled ? 1 : 0]);
    }

    public function test_remaining_payment_link_for_2027_plus_booking_builds_a_platform_held_checkout_session(): void
    {
        $this->setKillSwitch(true);

        // A booking for an add-on paid via a "remaining_payment" link -- exactly the scenario
        // reported: add an add-on, pay via the generated payment link, for a 2027+ tour date.
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(86.02)
            ->withPaymentMethod('Card')
            ->withDate('2027-06-01')
            ->create();
        $bookingId = $booking->bookingId;

        // group_id isn't set by the fixture's raw insert; createPaymentLink() looks bookings up
        // by it, so make this booking its own group (the normal solo-booking shape).
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);

        $token = 'test-token-' . uniqid();
        TourBookingPaymentLink::create([
            'order_id' => $bookingId,
            'section' => 'remaining_payment',
            'amount' => 25.00,
            'reason_of_payment' => 'Add-on',
            'email' => 'test@test.com',
            'phone_number' => '5550001111',
            'token' => $token,
            'status' => 'Unpaid',
            'override_status' => 'Yes', // skip the expiry check entirely for this test
            'expired_at' => now()->addDay(),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
        ]);

        // Delegate buildConnectedPaymentData() to the real, pure-function gateway so the
        // resulting Checkout Session params carry genuine PLATFORM_HELD shape (no
        // transfer_data/application_fee_amount) rather than a hand-rolled stand-in -- only the
        // Stripe network calls (customer lookup, checkout session creation) are faked.
        $realGateway = new StripePaymentGateway();
        $capturedMode = null;
        $capturedSessionParams = null;

        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('listCustomers')->willReturn((object) ['data' => []]);
        $fakeGateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test123']);
        $fakeGateway->method('buildConnectedPaymentData')
            ->willReturnCallback(function (string $destinationId, int $feeAmountCents, ?string $statementDescriptor, string $mode) use ($realGateway, &$capturedMode) {
                $capturedMode = $mode;
                return $realGateway->buildConnectedPaymentData($destinationId, $feeAmountCents, $statementDescriptor, $mode);
            });
        $fakeGateway->method('createCheckoutSession')
            ->willReturnCallback(function (array $params) use (&$capturedSessionParams) {
                $capturedSessionParams = $params;
                return (object) ['id' => 'cs_test123', 'url' => 'https://checkout.stripe.test/cs_test123'];
            });
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            PaymentLinkService::createPaymentLink($token);

            $this->assertSame(
                PaymentModeResolver::PLATFORM_HELD,
                $capturedMode,
                'A 2027+ tour date with the kill switch on must resolve to PLATFORM_HELD, not stay pinned to DESTINATION'
            );
            $this->assertNotNull($capturedSessionParams, 'Expected createCheckoutSession() to be called');
            $this->assertArrayNotHasKey(
                'transfer_data',
                $capturedSessionParams['payment_intent_data'],
                'A platform-held Checkout Session must not carry transfer_data'
            );
            $this->assertArrayNotHasKey(
                'application_fee_amount',
                $capturedSessionParams['payment_intent_data'],
                'A platform-held Checkout Session must not carry application_fee_amount'
            );
        } finally {
            $booking->cleanup();
            TourBookingPaymentLink::where('token', $token)->delete();
        }
    }

    public function test_payment_link_for_a_pre_2027_booking_stays_destination(): void
    {
        $this->setKillSwitch(true);

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(86.02)
            ->withPaymentMethod('Card')
            ->withDate('2026-06-01')
            ->create();
        $bookingId = $booking->bookingId;

        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);

        $token = 'test-token-' . uniqid();
        TourBookingPaymentLink::create([
            'order_id' => $bookingId,
            'section' => 'remaining_payment',
            'amount' => 25.00,
            'reason_of_payment' => 'Add-on',
            'email' => 'test@test.com',
            'phone_number' => '5550001111',
            'token' => $token,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDay(),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $capturedMode = null;
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('listCustomers')->willReturn((object) ['data' => []]);
        $fakeGateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test123']);
        $fakeGateway->method('buildConnectedPaymentData')
            ->willReturnCallback(function (string $destinationId, int $feeAmountCents, ?string $statementDescriptor, string $mode) use (&$capturedMode) {
                $capturedMode = $mode;
                return [];
            });
        $fakeGateway->method('createCheckoutSession')
            ->willReturn((object) ['id' => 'cs_test123', 'url' => 'https://checkout.stripe.test/cs_test123']);
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            PaymentLinkService::createPaymentLink($token);

            $this->assertSame(PaymentModeResolver::DESTINATION, $capturedMode);
        } finally {
            $booking->cleanup();
            TourBookingPaymentLink::where('token', $token)->delete();
        }
    }
}
