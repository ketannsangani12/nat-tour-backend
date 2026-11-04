<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Services\Booking\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Reproduces the reported scenario: two affiliate staff sharing one login, on two
 * different computers (different IPs), both hit "Make Payment" on the same unpaid
 * booking. Asserts only one Stripe PaymentIntent is ever created, and the second
 * request transparently reuses it instead of creating a duplicate.
 */
class MakePaymentSameLoginDifferentIpTest extends TestCase
{
    private ?CreateABooking $booking = null;

    protected function tearDown(): void
    {
        try {
            $this->booking?->cleanup();
        } finally {
            $this->booking = null;
            Mockery::close();
            parent::tearDown();
        }
    }

    private function makeRequest(int $bookingId, string $ip): Request
    {
        $request = Request::create('/', 'POST', ['bookingID' => $bookingId]);
        $request->server->set('REMOTE_ADDR', $ip);

        return $request;
    }

    private function setUpUnpaidCardBookingAsAdmin(): Admin
    {
        // Unpaid ("Pay Later") Card booking - no existing Completed transaction.
        $this->booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withoutTransaction()
            ->withoutHistory()
            ->create();

        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->assertSame($admin->id, Auth::id(), 'Auth::id() must resolve to the acting admin for this test to be meaningful.');

        return $admin;
    }

    /**
     * @param Mockery\MockInterface&PaymentGatewayInterface $gateway
     */
    private function mockCustomerAndCardLookups($gateway): void
    {
        $gateway->shouldReceive('listCustomers')->andReturn((object) ['data' => []]);
        $gateway->shouldReceive('createCustomer')->andReturn((object) ['id' => 'cus_test_' . uniqid()]);
        $gateway->shouldReceive('listPaymentMethods')->andReturn((object) ['data' => []]);
        $gateway->shouldReceive('buildConnectedPaymentData')->andReturn([]);
    }

    public function test_second_affiliate_on_different_ip_reuses_the_same_payment_intent(): void
    {
        $this->setUpUnpaidCardBookingAsAdmin();

        $capturedAmount = null;

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->mockCustomerAndCardLookups($gateway);
        $gateway->shouldReceive('createPayment')
            ->once() // <-- the whole point of the test: exactly one PaymentIntent, ever
            ->andReturnUsing(function (array $params) use (&$capturedAmount) {
                $capturedAmount = $params['amount'];
                return (object) [
                    'id' => 'pi_test_makepayment_' . uniqid(),
                    'client_secret' => 'secret_makepayment_1',
                    'customer' => 'cus_test_reused',
                ];
            });
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        // -- Affiliate A, computer/IP #1: starts the payment, customer hasn't confirmed yet --
        $resultA = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '10.0.0.1'));

        $this->assertNotEmpty($resultA['paymentIntentId'] ?? null, 'First call should create a real PaymentIntent.');

        // Now that we know the real computed amount, make the gateway mock consistent
        // for the reuse path: retrievePayment() must report the SAME PaymentIntent, still open.
        $paymentIntentId = $resultA['paymentIntentId'];
        $gateway->shouldReceive('retrievePayment')
            ->with($paymentIntentId)
            ->andReturn((object) [
                'id' => $paymentIntentId,
                'status' => 'requires_payment_method', // still open — not confirmed, not canceled
                // Stripe always represents amount as an int (cents); match that fidelity here
                // since the production code's comparison against the recomputed total is strict.
                'amount' => (int) $capturedAmount,
                'client_secret' => 'secret_makepayment_1',
                'customer' => 'cus_test_reused',
            ]);

        // -- Affiliate B, computer/IP #2, SAME shared login: also hits "Make Payment" moments later --
        $resultB = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '203.0.113.55'));

        $this->assertSame(
            $resultA['clientSecret'],
            $resultB['clientSecret'],
            'Second affiliate (different IP, same login) must be handed the SAME PaymentIntent, not a new one.'
        );

        $this->assertReservationCountIs(1);
    }

    /**
     * This is the scenario that actually produced the reported duplicate charges: the first
     * PaymentIntent sits unconfirmed long enough that the old 30-minute in-flight window would
     * have expired, so a second affiliate on a different IP no longer looked "in progress" and
     * the old code would create (and later, if the first was ever confirmed too, double-charge)
     * a second PaymentIntent. The fixed reservation lookup has no time window at all — it must
     * still reuse the original PaymentIntent no matter how old it is, as long as it's still open.
     */
    public function test_second_affiliate_reuses_payment_intent_even_after_first_reservation_is_over_30_minutes_old(): void
    {
        $this->setUpUnpaidCardBookingAsAdmin();

        $capturedAmount = null;

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->mockCustomerAndCardLookups($gateway);
        $gateway->shouldReceive('createPayment')
            ->once() // <-- must NOT be called a second time just because time passed
            ->andReturnUsing(function (array $params) use (&$capturedAmount) {
                $capturedAmount = $params['amount'];
                return (object) [
                    'id' => 'pi_test_makepayment_' . uniqid(),
                    'client_secret' => 'secret_makepayment_1',
                    'customer' => 'cus_test_reused',
                ];
            });
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $resultA = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '10.0.0.1'));
        $paymentIntentId = $resultA['paymentIntentId'];

        // Simulate the customer sitting on the card-entry page for 45 minutes without confirming.
        DB::table('transactions')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->where('status', 'Initialized')
            ->whereNull('charge_type')
            ->update(['created_at' => Carbon::now()->subMinutes(45)]);

        $gateway->shouldReceive('retrievePayment')
            ->with($paymentIntentId)
            ->andReturn((object) [
                'id' => $paymentIntentId,
                'status' => 'requires_payment_method', // still open in Stripe — nothing expires it
                'amount' => (int) $capturedAmount,
                'client_secret' => 'secret_makepayment_1',
                'customer' => 'cus_test_reused',
            ]);

        // -- A different affiliate (shared login, different IP), 45+ minutes later --
        $resultB = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '203.0.113.55'));

        $this->assertSame(
            $resultA['clientSecret'],
            $resultB['clientSecret'],
            'Even after the first reservation aged past the old 30-minute window, the second request must reuse the still-open PaymentIntent instead of creating a duplicate.'
        );

        $this->assertReservationCountIs(1);
    }

    /**
     * Reproduces the manual-test scenario: the customer's card was actually charged
     * (Stripe already reports the PaymentIntent as succeeded), but the browser's one
     * follow-up call to paymentNewBooking() never landed, so the local booking was never
     * completed. A second attempt in that gap must still throw (no silent second charge —
     * the exact wording/HTTP shape of that error is a separate, frontend-dependent concern
     * that's out of scope here; an earlier attempt to make it a friendly non-thrown response
     * broke the payment modal, since the frontend feeds `clientSecret` straight into Stripe
     * Elements without checking for an error field first) — but it must ALSO reconcile the
     * booking's own records (transaction/history/totals) as a side effect, since Stripe
     * really did take the money and nothing else in the system will ever record that.
     */
    public function test_second_attempt_after_stripe_already_succeeded_reconciles_the_booking_then_throws(): void
    {
        $this->setUpUnpaidCardBookingAsAdmin();

        $capturedAmount = null;

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->mockCustomerAndCardLookups($gateway);
        $gateway->shouldReceive('createPayment')
            ->once() // <-- must NEVER be called again once Stripe already succeeded
            ->andReturnUsing(function (array $params) use (&$capturedAmount) {
                $capturedAmount = $params['amount'];
                return (object) [
                    'id' => 'pi_test_makepayment_' . uniqid(),
                    'client_secret' => 'secret_makepayment_1',
                    'customer' => 'cus_test_reused',
                ];
            });
        $gateway->shouldReceive('updatePayment')->andReturn(null);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $resultA = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '10.0.0.1'));
        $paymentIntentId = $resultA['paymentIntentId'];

        // The card was confirmed on Stripe's side, but the local row is still "Initialized"
        // because the one call that would have completed it never landed. Give retrievePayment()
        // everything both stripeCreatePaymentIntentForMakePayment's own check AND the reconciliation
        // path (paymentNewBooking -> extractCardDetails / updatePaymentIntentForMakePayment) need.
        $gateway->shouldReceive('retrievePayment')
            ->withArgs(fn ($id, $options = []) => $id === $paymentIntentId)
            ->andReturn((object) [
                'id' => $paymentIntentId,
                'status' => 'succeeded',
                'amount' => (int) $capturedAmount,
                'client_secret' => 'secret_makepayment_1',
                'customer' => 'cus_test_reused',
                'metadata' => ['from' => ''],
                'charges' => (object) [
                    'data' => [
                        (object) [
                            'application_fee' => null,
                            'payment_method_details' => (object) [
                                'card' => (object) ['last4' => '4242', 'brand' => 'visa'],
                            ],
                        ],
                    ],
                ],
            ]);

        try {
            StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '203.0.113.55'));
            $this->fail('Expected an exception when the booking was already fully paid.');
        } catch (\Exception $exception) {
            $this->assertSame('This booking has already been fully paid.', $exception->getMessage());
        }

        // createPayment()->once() above already enforces no second PaymentIntent was created.
        // But the booking's OWN records must now be complete, since Stripe really took the money:
        $completed = DB::table('transactions')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->where('transaction_number', $paymentIntentId)
            ->where('status', 'Completed')
            ->first();
        $this->assertNotNull($completed, 'The transaction row must be flipped to Completed with the real payment recorded, not left as an empty Initialized placeholder.');
        $this->assertGreaterThan(0, (float) $completed->amount, 'The Completed transaction must carry the real charged amount, not the 0.00 placeholder.');

        $historyEntry = DB::table('tour_booking_history')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->where('operation', 'Payment')
            ->first();
        $this->assertNotNull($historyEntry, 'A Payment history entry must exist so the booking timeline reflects the charge.');

        $bookingRow = DB::table('tour_bookings_master')->where('id', $this->booking->bookingId)->first();
        $this->assertGreaterThan(0, (float) $bookingRow->total, 'tour_bookings_master must reflect the finalized paid total, not be left unset.');

        $this->assertReservationCountIs(0);
    }

    /**
     * Safety guard: if the amount Stripe actually charged doesn't match what we'd compute right
     * now (booking state changed since the original charge — e.g. a discount got applied), the
     * reconciliation must NOT run — recording the wrong amount would be worse than leaving it for
     * manual review. It must still throw exactly as before, and leave the reservation untouched.
     */
    public function test_skips_reconciliation_when_charged_amount_does_not_match_current_total(): void
    {
        $this->setUpUnpaidCardBookingAsAdmin();

        $capturedAmount = null;

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->mockCustomerAndCardLookups($gateway);
        $gateway->shouldReceive('createPayment')
            ->once()
            ->andReturnUsing(function (array $params) use (&$capturedAmount) {
                $capturedAmount = $params['amount'];
                return (object) [
                    'id' => 'pi_test_makepayment_' . uniqid(),
                    'client_secret' => 'secret_makepayment_1',
                    'customer' => 'cus_test_reused',
                ];
            });
        // paymentNewBooking() must never be reached for this case.
        $gateway->shouldNotReceive('updatePayment');
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $resultA = StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '10.0.0.1'));
        $paymentIntentId = $resultA['paymentIntentId'];

        // Stripe reports an amount that does NOT match what we'd compute right now.
        $gateway->shouldReceive('retrievePayment')
            ->withArgs(fn ($id, $options = []) => $id === $paymentIntentId)
            ->andReturn((object) [
                'id' => $paymentIntentId,
                'status' => 'succeeded',
                'amount' => (int) $capturedAmount + 500,
                'client_secret' => 'secret_makepayment_1',
                'customer' => 'cus_test_reused',
            ]);

        try {
            StripeService::stripeCreatePaymentIntentForMakePayment($this->makeRequest($this->booking->bookingId, '203.0.113.55'));
            $this->fail('Expected an exception when the booking was already fully paid.');
        } catch (\Exception $exception) {
            $this->assertSame('This booking has already been fully paid.', $exception->getMessage());
        }

        // Nothing should have been reconciled — the reservation is still sitting exactly as it was.
        $completed = DB::table('transactions')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->where('status', 'Completed')
            ->first();
        $this->assertNull($completed, 'Reconciliation must not run when the charged amount does not match the current computed total.');
        $this->assertReservationCountIs(1);
    }

    private function assertReservationCountIs(int $expected): void
    {
        $reservationCount = DB::table('transactions')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->where('status', 'Initialized')
            ->whereNull('charge_type')
            ->count();
        $this->assertSame($expected, $reservationCount, 'Unexpected number of in-flight reservation rows for this booking.');
    }
}
