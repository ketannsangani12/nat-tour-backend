<?php

namespace Tests\Unit\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeResolver;
use App\Utils\StripeUtils;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: transfer_group was set unconditionally on every PaymentIntent created via
 * createStripePaymentIntentCommon()/createStripeIntent() (the two StripeUtils.php choke points
 * that actually set it), regardless of settlement mode. For a platform-held (2027+) charge with
 * no partner split, nothing in the codebase ever reads transfer_group back -- it's pure noise on
 * the Stripe side. For a partner-package booking, ThreeWayPaymentService::transferToPartner()/
 * transferToNewPartner() DOES read it back off the original charge (regardless of settlement
 * mode, since that engine runs on is_partner_booking alone) to tag the resulting Transfer, so it
 * must be kept there. Fixed by omitting transfer_group only when mode is PLATFORM_HELD AND the
 * booking is not a partner booking; every other combination (destination mode, or any partner
 * booking) keeps it exactly as before.
 */
class TransferGroupOmissionTest extends TestCase
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

    private function fakeGatewayCapturing(&$capturedPayload): PaymentGatewayInterface
    {
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('listCustomers')->willReturn((object) ['data' => []]);
        $fakeGateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test_' . uniqid()]);
        $fakeGateway->method('listPaymentMethods')->willReturn((object) ['data' => []]);
        $fakeGateway->method('buildConnectedPaymentData')->willReturn([]);
        $fakeGateway->method('createPayment')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return (object) ['id' => 'pi_test_' . uniqid(), 'client_secret' => 'secret_test'];
            });
        return $fakeGateway;
    }

    private function baseArgs(): array
    {
        return [
            'stripeDetails' => [
                'stripeTotal' => 100.00,
                'stripeFee' => 5.00,
                'description' => 'Test charge',
                'BookingId' => 999999,
                'tourists' => 1,
            ],
            'data' => [
                'customer_email' => 'transfer-group-test-' . uniqid() . '@test.com',
                'customer_name' => 'Test Customer',
                'customer_phone' => '5555555555',
            ],
            'info' => (object) [
                'statement_descriptor' => 'TEST OPERATOR',
                'tour_operator' => 'Test Operator',
                'tour_package' => 'Test Package',
                'date' => '2027-06-01',
                'time_from' => '10:00:00',
                'stripe_destination_id' => 'acct_test_fake',
            ],
        ];
    }

    public function test_platform_held_non_partner_omits_transfer_group(): void
    {
        $args = $this->baseArgs();
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        StripeUtils::createStripePaymentIntentCommon($args['stripeDetails'], $args['data'], $args['info'], PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('transfer_group', $capturedPayload);
    }

    public function test_platform_held_partner_booking_keeps_transfer_group(): void
    {
        $args = $this->baseArgs();
        $args['stripeDetails']['payoutAmount'] = 42.00;
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        StripeUtils::createStripePaymentIntentCommon($args['stripeDetails'], $args['data'], $args['info'], PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayHasKey('transfer_group', $capturedPayload);
    }

    public function test_destination_mode_keeps_transfer_group_regardless_of_partner_status(): void
    {
        $args = $this->baseArgs();
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        StripeUtils::createStripePaymentIntentCommon($args['stripeDetails'], $args['data'], $args['info'], PaymentModeResolver::DESTINATION);

        $this->assertArrayHasKey('transfer_group', $capturedPayload);
    }

    /**
     * Regression: a live "New Booking" test for a partner package dated 2027+ hit
     * "You passed an empty string for 'transfer_group'" from Stripe. Root cause turned out to be a
     * DIFFERENT creation choke point than the one above: createStripePaymentIntentCart() (the
     * session-cart path -- used whenever cartView() is on and the admin's cart has items, which
     * "Add Package" populates) never set transfer_group at all, in either settlement mode. It went
     * unnoticed for destination-mode cart bookings only by coincidence of how/when this was
     * exercised; a Stripe test confirmed Stripe does NOT auto-generate transfer_group for a
     * destination charge either (both a destination charge and a plain charge come back with
     * transfer_group === null unless explicitly set) -- so this was never a mode-specific auto-fill
     * quirk, just a missing explicit set on this one method that the other two choke points already
     * had. ThreeWayPaymentService::transferToPartner() reads $paymentIntent->transfer_group back off
     * the charge regardless of mode and sends Stripe an explicit '' when it's null, which Stripe
     * rejects outright.
     */
    public function test_cart_platform_held_partner_item_keeps_transfer_group(): void
    {
        $this->setKillSwitch(true);
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $info = (object) ['statement_descriptor' => 'TEST OPERATOR', 'tour_operator' => 'Test Operator', 'package_name' => 'Test Package', 'stripe_destination_id' => 'acct_test_fake'];
        $amounts = ['stripeTotal' => 100.00, 'stripeFee' => 5.00, 'description' => 'Test cart charge'];
        $data = [
            'customer_email' => 'cart-transfer-group-' . uniqid() . '@test.com',
            'customer_name' => 'Test Customer',
            'customer_phone' => '5555555555',
            'data' => [['date' => '2027-06-01', 'partnerFeesTotal' => 15.00]],
        ];

        StripeUtils::createStripePaymentIntentCart($amounts, $data, $info);

        $this->assertArrayHasKey('transfer_group', $capturedPayload);
    }

    public function test_cart_platform_held_non_partner_omits_transfer_group(): void
    {
        $this->setKillSwitch(true);
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $info = (object) ['statement_descriptor' => 'TEST OPERATOR', 'tour_operator' => 'Test Operator', 'package_name' => 'Test Package', 'stripe_destination_id' => 'acct_test_fake'];
        $amounts = ['stripeTotal' => 100.00, 'stripeFee' => 5.00, 'description' => 'Test cart charge'];
        $data = [
            'customer_email' => 'cart-transfer-group-' . uniqid() . '@test.com',
            'customer_name' => 'Test Customer',
            'customer_phone' => '5555555555',
            'data' => [['date' => '2027-06-01', 'partnerFeesTotal' => 0]],
        ];

        StripeUtils::createStripePaymentIntentCart($amounts, $data, $info);

        $this->assertArrayNotHasKey('transfer_group', $capturedPayload);
    }

    public function test_cart_destination_mode_keeps_transfer_group(): void
    {
        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $info = (object) ['statement_descriptor' => 'TEST OPERATOR', 'tour_operator' => 'Test Operator', 'package_name' => 'Test Package', 'stripe_destination_id' => 'acct_test_fake'];
        $amounts = ['stripeTotal' => 100.00, 'stripeFee' => 5.00, 'description' => 'Test cart charge'];
        $data = [
            'customer_email' => 'cart-transfer-group-' . uniqid() . '@test.com',
            'customer_name' => 'Test Customer',
            'customer_phone' => '5555555555',
            'data' => [['date' => '2026-06-01', 'partnerFeesTotal' => 0]],
        ];

        StripeUtils::createStripePaymentIntentCart($amounts, $data, $info);

        $this->assertArrayHasKey('transfer_group', $capturedPayload);
    }
}
