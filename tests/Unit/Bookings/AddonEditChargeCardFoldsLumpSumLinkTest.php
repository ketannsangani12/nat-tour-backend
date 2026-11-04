<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Booking\StripeService;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Regression: StripeService::stripeCreatePaymentIntentForAddonEditCharge() -- the "Card (Stripe)"
 * option in the add-ons "Payment Required" modal -- only folded in a coexisting PENDING PAYMENT
 * LINK's amount when that link was the ITEMIZED shape (section='remaining_payment'). A booking
 * whose only pending balance is the original LUMP-SUM link (section=NULL, created at booking
 * creation) got its card charged for ONLY the new add-on's own amount, silently leaving the
 * booking's original amount uncollected -- exact same class of bug already fixed for
 * PaymentLinkService::upsertRemainingPaymentLink() (the Payment Link path) earlier this session.
 *
 * CMD: php artisan test --filter=AddonEditChargeCardFoldsLumpSumLinkTest
 */
class AddonEditChargeCardFoldsLumpSumLinkTest extends TestCase
{
    protected static array $builders = [];

    private function fakeGatewayCapturing(&$capturedPayload): PaymentGatewayInterface
    {
        $real = new StripePaymentGateway();
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('listCustomers')->willReturn((object) ['data' => []]);
        $fakeGateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test_' . uniqid()]);
        $fakeGateway->method('listPaymentMethods')->willReturn((object) ['data' => []]);
        $fakeGateway->method('buildConnectedPaymentData')
            ->willReturnCallback(fn (string $destinationId, int $feeAmountCents, ?string $statementDescriptor = null, string $mode = 'destination') =>
                $real->buildConnectedPaymentData($destinationId, $feeAmountCents, $statementDescriptor, $mode));
        $fakeGateway->method('createPayment')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return (object) ['id' => 'pi_test_' . uniqid(), 'client_secret' => 'secret_test'];
            });
        return $fakeGateway;
    }

    public function test_a_coexisting_lump_sum_link_is_folded_into_the_card_payment_intent_amount(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);
        // The fixture always creates its BookingTransact row with nat_fees_percentage=0 (no
        // fluent setter for it) -- set a real, non-zero rate directly so the fee-folding math
        // below is actually exercised, not trivially skipped.
        DB::table('tour_bookings_transact')->where('tour_booking_id', $builder->bookingId)->where('deleted_by', 0)->update(['nat_fees_percentage' => 10.00]);

        $lumpSumLinkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'               => 146.60,
            'reason_of_payment'    => 'Booking payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        try {
            $capturedPayload = null;
            $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

            $request = new Request();
            $request->merge([
                'bookingId'       => $builder->bookingId,
                'net_amount'      => 40.00,
                'financial_items' => json_encode([
                    'to_charge' => [
                        ['charge_amount' => 20.00, 'field_price' => 20.00, 'option_price' => 0],
                        ['charge_amount' => 20.00, 'field_price' => 20.00, 'option_price' => 0],
                    ],
                    'to_refund' => [],
                ]),
            ]);

            StripeService::stripeCreatePaymentIntentForAddonEditCharge($request);

            $this->assertNotNull($capturedPayload, 'createPayment() must have been called');

            // net_amount (40.00) + its own fee (10% -> 4.00) + the lump-sum link (146.60) = 190.60
            $expectedTotal = round(40.00 + 4.00 + 146.60, 2);
            $this->assertEqualsWithDelta(
                (int) round($expectedTotal * 100),
                $capturedPayload['amount'],
                1,
                'The PaymentIntent must charge the combined total (new add-ons + its fee + the pending lump-sum link), not just the add-ons alone'
            );

            // application_fee_amount (Stripe Connect's platform cut) must ALSO include the
            // lump-sum portion's own fee, backed out at the same 10% rate (146.60 -
            // (146.60/1.10) = 13.33), not just the add-on's own 4.00 -- otherwise the platform
            // under-collects its cut on every combined charge like this one.
            $expectedFeeCents = (int) round((4.00 + 13.33) * 100);
            $this->assertArrayHasKey('application_fee_amount', $capturedPayload);
            $this->assertEqualsWithDelta(
                $expectedFeeCents,
                $capturedPayload['application_fee_amount'],
                2,
                'application_fee_amount must include the lump-sum portion\'s derived fee, not just the add-on\'s own'
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $lumpSumLinkId)->delete();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        static::$builders = [];
        parent::tearDownAfterClass();
    }
}
