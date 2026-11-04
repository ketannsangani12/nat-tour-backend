<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * NOTE: this app's test DB is the real, shared `dixies` tenant DB (no per-test
 * transaction/rollback wrapper — see CLAUDE.md), and it can already contain stale
 * Initialized reservation rows left over from other tests/runs. Since the command
 * under test scans the WHOLE transactions table by design, every gateway mock here
 * declares its specific expectation(s) for the row it cares about FIRST, then a
 * catch-all fallback LAST for any other stale row already sitting in the table
 * (Mockery matches expectations in declaration order, so the specific one must come
 * first or the catch-all swallows every call). The fallback reports "succeeded" so
 * the command leaves unrelated rows untouched rather than mutating them.
 */
class CleanupAbandonedPaymentReservationsTest extends TestCase
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

    /**
     * @param  Mockery\MockInterface&PaymentGatewayInterface  $gateway
     */
    private function addCatchAllFallback($gateway): void
    {
        $gateway->shouldReceive('retrievePayment')
            ->andReturn((object) ['id' => 'unrelated', 'status' => 'succeeded']);
        $gateway->shouldReceive('cancelPayment')->andReturn(null);
    }

    private function insertStaleReservation(string $paymentIntentId): int
    {
        $this->booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();

        return DB::table('transactions')->insertGetId([
            'tour_booking_id' => $this->booking->bookingId,
            'amount' => 0,
            'fee_amount' => 0,
            'transaction_number' => $paymentIntentId,
            'via' => 'Card',
            'payment_type' => 0,
            'response_init' => '',
            'response_end' => '',
            'status' => 'Initialized',
            'visitor' => '127.0.0.1',
            'request_from' => 'Backend',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => Carbon::now()->subMinutes(45),
        ]);
    }

    public function test_cancels_stripe_payment_intent_and_deletes_reservation_when_still_open(): void
    {
        $paymentIntentId = 'pi_test_open_' . uniqid();
        $reservationId = $this->insertStaleReservation($paymentIntentId);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('retrievePayment')
            ->once()
            ->with($paymentIntentId)
            ->andReturn((object) ['id' => $paymentIntentId, 'status' => 'requires_payment_method']);
        $gateway->shouldReceive('cancelPayment')
            ->once()
            ->with($paymentIntentId);
        $this->addCatchAllFallback($gateway);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        Artisan::call('transactions:cleanup-abandoned-reservations');

        $this->assertDatabaseMissing('transactions', ['id' => $reservationId]);
    }

    public function test_deletes_reservation_without_touching_stripe_when_already_canceled(): void
    {
        $paymentIntentId = 'pi_test_canceled_' . uniqid();
        $reservationId = $this->insertStaleReservation($paymentIntentId);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('retrievePayment')
            ->once()
            ->with($paymentIntentId)
            ->andReturn((object) ['id' => $paymentIntentId, 'status' => 'canceled']);
        $gateway->shouldNotReceive('cancelPayment')->with($paymentIntentId);
        $this->addCatchAllFallback($gateway);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        Artisan::call('transactions:cleanup-abandoned-reservations');

        $this->assertDatabaseMissing('transactions', ['id' => $reservationId]);
    }

    public function test_leaves_reservation_in_place_when_payment_intent_already_succeeded(): void
    {
        $paymentIntentId = 'pi_test_succeeded_' . uniqid();
        $reservationId = $this->insertStaleReservation($paymentIntentId);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('retrievePayment')
            ->once()
            ->with($paymentIntentId)
            ->andReturn((object) ['id' => $paymentIntentId, 'status' => 'succeeded']);
        $gateway->shouldNotReceive('cancelPayment')->with($paymentIntentId);
        $this->addCatchAllFallback($gateway);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        Artisan::call('transactions:cleanup-abandoned-reservations');

        $this->assertDatabaseHas('transactions', ['id' => $reservationId, 'status' => 'Initialized']);
    }

    public function test_deletes_reservation_with_no_transaction_number_without_calling_stripe(): void
    {
        $this->booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();

        $reservationId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $this->booking->bookingId,
            'amount' => 0,
            'fee_amount' => 0,
            'transaction_number' => '',
            'via' => 'Card',
            'payment_type' => 0,
            'response_init' => '',
            'response_end' => '',
            'status' => 'Initialized',
            'visitor' => '127.0.0.1',
            'request_from' => 'Backend',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => Carbon::now()->subMinutes(45),
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->addCatchAllFallback($gateway);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        Artisan::call('transactions:cleanup-abandoned-reservations');

        $this->assertDatabaseMissing('transactions', ['id' => $reservationId]);
    }

    public function test_leaves_recent_reservation_untouched(): void
    {
        $this->booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();

        $reservationId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $this->booking->bookingId,
            'amount' => 0,
            'fee_amount' => 0,
            'transaction_number' => 'pi_test_recent_' . uniqid(),
            'via' => 'Card',
            'payment_type' => 0,
            'response_init' => '',
            'response_end' => '',
            'status' => 'Initialized',
            'visitor' => '127.0.0.1',
            'request_from' => 'Backend',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => Carbon::now(), // not stale — the 30-minute cutoff must not touch it
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->addCatchAllFallback($gateway);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        Artisan::call('transactions:cleanup-abandoned-reservations');

        $this->assertDatabaseHas('transactions', ['id' => $reservationId, 'status' => 'Initialized']);
    }
}
