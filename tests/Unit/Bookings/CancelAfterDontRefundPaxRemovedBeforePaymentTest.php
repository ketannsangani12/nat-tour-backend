<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * A "Don't Refund" pax removal made while the booking was still UNPAID forfeits nothing: the
 * payment taken afterwards is already sized for the pax left on the booking. A later cancel must
 * refund that whole payment. Before the fix, 100% Refund and Ticket Cost cancels still took the
 * removed pax's per-pax share out of the payment, as if they had been paid for.
 *
 * Booking KEN-252477: 10 pax soft-blocked, 4 removed with Don't Refund while unpaid, $429.78 paid
 * for the remaining 6, then cancelled with 100% Refund — only $143.26 was refunded.
 */
class CancelAfterDontRefundPaxRemovedBeforePaymentTest extends TestCase
{
	private ?CreateABooking $booking = null;

	/** Refund params sent to Stripe */
	private array $stripeRefunds = [];

	/** CASH_FEES setting to restore, when a test changed it */
	private ?string $originalCashFees = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

		$gateway = Mockery::mock(PaymentGatewayInterface::class);
		$gateway->shouldReceive('refundPayment')->andReturnUsing(function (array $params) {
			$this->stripeRefunds[] = $params;
			return null;
		});
		$this->app->instance(PaymentGatewayInterface::class, $gateway);
	}

	protected function tearDown(): void
	{
		while (DB::transactionLevel() > 0) {
			DB::rollBack();
		}
		$this->booking?->cleanup();

		if ($this->originalCashFees !== null) {
			DB::table('settings')->where('key', 'CASH_FEES')->update(['value' => $this->originalCashFees]);
		}

		parent::tearDown();
	}

	/**
	 * Card booking paid in full for $tourists pax: $ticketCost + $fees charged.
	 */
	private function cardBooking(int $tourists, float $ticketCost, float $fees): CreateABooking
	{
		$this->booking = (new CreateABooking())
			->withTourists($tourists)
			->withRate(round($ticketCost / $tourists, 2))
			->withPaymentMethod('Card')
			->create();

		$id = $this->booking->bookingId;
		$total = round($ticketCost + $fees, 2);
		DB::table('tour_bookings_master')->where('id', $id)->update(['total' => $total, 'booking_total' => $total, 'service_commission' => $fees]);
		DB::table('transactions')->where('id', $this->booking->transactionId)->update(['amount' => $total, 'fee_amount' => $fees, 'payment_type' => 0]);
		DB::table('tour_booking_history')->where('tour_booking_id', $id)->where('operation', 'Payment')->update([
			'ticket_cost' => $ticketCost,
			'platform_fees' => $fees,
			'details' => json_encode(['payment_type' => 'Card', 'payment_method' => 'Full Payment', 'amount' => $total]),
		]);

		return $this->booking;
	}

	/**
	 * Record a Don't Refund removal made while the booking was unpaid, as Remove Pax stores it
	 * (no transaction, empty payment_type), and put it BEFORE the booking's Payment row.
	 * $staleKeptAmount mimics the refund_keep_us older rows carry even though nothing was paid.
	 */
	private function removePaxBeforePayment(int $bookingId, string $deleted, float $staleKeptAmount = 0.00): void
	{
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $bookingId,
			'tourists' => 4,
			'operation' => 'Refund',
			'payment_type' => '',
			'payment_method' => "Don't Refund",
			'details' => json_encode([
				'amount' => 0,
				'refund_keep_us' => $staleKeptAmount,
				'payment_type' => '',
				'refund_type' => "Don't Refund",
				'deleted' => $deleted,
				'fees' => 0,
				'partner_fees' => 0,
				'refund_partner_fees_keep_us' => 0,
			]),
			'operation_at' => now()->subMinutes(2),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => now()->subMinutes(2),
		]);

		// Re-insert the Payment row so it is recorded after the removal, as in production.
		$payment = (array) DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('operation', 'Payment')->first();
		DB::table('tour_booking_history')->where('id', $payment['id'])->delete();
		unset($payment['id']);
		DB::table('tour_booking_history')->insert($payment);
	}

	/**
	 * Don't Refund removal of an already-paid pax (recorded after the Payment row).
	 */
	private function removePaidPaxWithDontRefund(int $bookingId, string $deleted, int $remainingTourists): void
	{
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $bookingId,
			'tourists' => $remainingTourists,
			'operation' => 'Refund',
			'payment_method' => "Don't Refund",
			'details' => json_encode(['amount' => 0, 'refund_type' => "Don't Refund", 'deleted' => $deleted, 'payment_type' => 'Card']),
			'operation_at' => now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => now(),
		]);
		DB::table('tour_bookings_master')->where('id', $bookingId)->update(['tourists' => $remainingTourists]);
	}

	private function cancel(int $bookingId, string $refundType): void
	{
		$request = new Request();
		$request->merge([
			'cancel_booking' => 1,
			'refund_type' => $refundType,
			'comment' => 'cancel after unpaid pax removal',
			'api' => 1,
		]);
		app(TourBookingRepository::class)->cancel($request, $bookingId);
	}

	private function latestRefund(int $bookingId): object
	{
		return DB::table('transactions')->where('tour_booking_id', $bookingId)->where('status', 'Refund')->latest('id')->first();
	}

	private function setCashFees(string $value): void
	{
		$this->originalCashFees ??= (string) DB::table('settings')->where('key', 'CASH_FEES')->value('value');
		DB::table('settings')->where('key', 'CASH_FEES')->update(['value' => $value]);
	}

	public function test_reproduces_booking_ken_252477(): void
	{
		// 6 pax paid: $401.64 tickets + $28.14 fees = $429.78; 4 pax removed before payment
		$booking = $this->cardBooking(6, 401.64, 28.14);
		$this->removePaxBeforePayment($booking->bookingId, 'Guest(s) person 4', 267.76);

		$this->cancel($booking->bookingId, '100_refund');

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		// The full $429.78 goes back to the card, not $143.26
		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(42978, (int) round($this->stripeRefunds[0]['amount']));
		$refund = $this->latestRefund($booking->bookingId);
		$this->assertEqualsWithDelta(429.78, (float) $refund->amount, 0.001);
		$this->assertEqualsWithDelta(28.14, (float) $refund->fee_amount, 0.001);
	}

	public function test_weather_cancel_refunds_everything_when_every_removed_pax_was_unpaid(): void
	{
		// Local repro: 6 removed before payment, 6 paid — used to refund $0 and skip Stripe
		$booking = $this->cardBooking(6, 401.64, 28.14);
		$this->removePaxBeforePayment($booking->bookingId, 'Guest(s) person 6', 401.64);

		$this->cancel($booking->bookingId, '100_refund');

		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(42978, (int) round($this->stripeRefunds[0]['amount']));
	}

	public function test_weather_cancel_still_excludes_a_pax_removed_after_payment(): void
	{
		// 2 pax paid: $170.00 + $12.96 = $182.96. 3 removed while unpaid, then 1 paid pax removed.
		$booking = $this->cardBooking(2, 170.00, 12.96);
		$this->removePaxBeforePayment($booking->bookingId, 'Adult person 3');
		$this->removePaidPaxWithDontRefund($booking->bookingId, 'Adult person 1', 1);

		$this->cancel($booking->bookingId, '100_refund');

		// Only the remaining paid pax's half ($85.00 + $6.48)
		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(9148, (int) round($this->stripeRefunds[0]['amount']));
		$this->assertEqualsWithDelta(6.48, (float) $this->latestRefund($booking->bookingId)->fee_amount, 0.001);
	}

	public function test_weather_cancel_of_a_cash_deposit_booking_ignores_a_stale_kept_amount_from_before_payment(): void
	{
		$this->setCashFees('0');
		// 2 pax @ $75 Cash deposit booking (fixture payment_type 1) = $150.00 paid
		$this->booking = (new CreateABooking())->withTourists(2)->withRate(75.00)->withPaymentMethod('Cash')->create();
		$id = $this->booking->bookingId;
		$this->removePaxBeforePayment($id, 'Adult person 2', 150.00);

		$this->cancel($id, '100_refund');

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $id)->value('status'));
		$this->assertEqualsWithDelta(150.00, (float) $this->latestRefund($id)->amount, 0.001);
		$this->assertCount(0, $this->stripeRefunds);
	}

	public function test_ticket_cost_cancel_of_a_card_booking_refunds_the_full_ticket_cost(): void
	{
		// 3 pax @ $75 Card = $225 ticket cost (fixture card booking, payment_type 2)
		$this->booking = (new CreateABooking())->withTourists(3)->withRate(75.00)->withPaymentMethod('Card')->create();
		$id = $this->booking->bookingId;
		$this->removePaxBeforePayment($id, 'Adult person 2', 150.00);

		$this->cancel($id, 'full_refund');

		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(22500, (int) round($this->stripeRefunds[0]['amount']));
	}

	public function test_ticket_cost_cancel_of_a_cash_booking_refunds_the_full_ticket_cost(): void
	{
		$this->setCashFees('0');
		// 3 pax @ $75 Cash = $225
		$this->booking = (new CreateABooking())->withTourists(3)->withRate(75.00)->withPaymentMethod('Cash')->create();
		$id = $this->booking->bookingId;
		$this->removePaxBeforePayment($id, 'Adult person 2', 150.00);

		$this->cancel($id, 'full_refund');

		$this->assertEqualsWithDelta(225.00, (float) $this->latestRefund($id)->amount, 0.001);
		$this->assertCount(0, $this->stripeRefunds);
	}
}
