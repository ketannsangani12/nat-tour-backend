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
 * Weather / 100% Refund cancel of a single-transaction Card booking must not refund a pax
 * that was earlier removed with "Don't Refund" — that pax's ticket cost + platform fee were
 * kept as revenue, but their history row's details->amount is $0, so they used to be
 * refunded again from the original (never-decremented) Payment row.
 */
class WeatherCancelAfterDontRefundPaxRemovalTest extends TestCase
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
	 * 2-pax Card booking: $170.00 tickets + $12.96 platform fees = $182.96 charged.
	 */
	private function cardBooking(): CreateABooking
	{
		$this->booking = (new CreateABooking())
			->withTourists(2)
			->withRate(85.00)
			->withPaymentMethod('Card')
			->create();

		$id = $this->booking->bookingId;
		DB::table('tour_bookings_master')->where('id', $id)->update(['total' => 182.96, 'booking_total' => 182.96, 'service_commission' => 12.96]);
		DB::table('transactions')->where('id', $this->booking->transactionId)->update(['amount' => 182.96]);
		DB::table('tour_booking_history')->where('tour_booking_id', $id)->where('operation', 'Payment')->update([
			'ticket_cost' => 170.00,
			'platform_fees' => 12.96,
			'details' => json_encode(['payment_type' => 'Card', 'payment_method' => 'Full Payment', 'amount' => 182.96]),
		]);

		return $this->booking;
	}

	/**
	 * Remove pax with "Don't Refund" the way the history records it: $0 amount, pax in "deleted".
	 */
	private function removePaxWithDontRefund(int $bookingId, string $deleted, int $remainingTourists): void
	{
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $bookingId,
			'tourists' => $remainingTourists,
			'operation' => 'Refund',
			'payment_method' => "Don't Refund",
			'details' => json_encode([
				'amount' => 0,
				'refund_type' => "Don't Refund",
				'deleted' => $deleted,
				'payment_type' => 'Card',
			]),
			'operation_at' => now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => now(),
		]);
		DB::table('tour_bookings_master')->where('id', $bookingId)->update(['tourists' => $remainingTourists]);
	}

	private function cancelWithWeatherRefund(int $bookingId): void
	{
		$request = new Request();
		$request->merge([
			'cancel_booking' => 1,
			'refund_type' => '100_refund',
			'comment' => 'weather closure',
			'api' => 1,
		]);
		app(TourBookingRepository::class)->cancel($request, $bookingId);
	}

	/**
	 * 2-pax Cash / Invoiced booking at $75.00 = $150.00 paid.
	 * payment_type 1 = deposit booking, 0 = paid in full.
	 */
	private function offlineBooking(string $via, int $paymentType): CreateABooking
	{
		$this->booking = (new CreateABooking())
			->withTourists(2)
			->withRate(75.00)
			->withPaymentMethod($via)
			->create();

		DB::table('transactions')->where('id', $this->booking->transactionId)->update(['via' => $via, 'payment_type' => $paymentType]);
		DB::table('tour_booking_history')->where('tour_booking_id', $this->booking->bookingId)->where('operation', 'Payment')
			->update(['details' => json_encode(['payment_type' => $via, 'payment_method' => 'Full Payment', 'amount' => 150.00])]);

		return $this->booking;
	}

	/**
	 * Remove 1 pax of an offline booking with "Don't Refund", as the pax removal flows store it:
	 * $0 amount, kept money in refund_keep_us, a $0 Refund transaction, total reduced.
	 */
	private function removeOfflinePaxWithDontRefund(int $bookingId, string $via, float $kept, float $keptInAmount = 0.00): void
	{
		$transactionId = DB::table('transactions')->insertGetId([
			'tour_booking_id' => $bookingId, 'amount' => 0, 'fee_amount' => 0, 'via' => $via, 'payment_type' => 0,
			'response_init' => '{}', 'response_end' => '{}', 'status' => 'Refund',
			'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
		]);
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $bookingId,
			'transaction_id' => $transactionId,
			'tourists' => 1,
			'operation' => 'Refund',
			'payment_type' => $via,
			'payment_method' => "Don't Refund",
			'details' => json_encode([
				'amount' => $keptInAmount,
				'refund_keep_us' => $kept,
				'payment_type' => $via,
				'refund_type' => "Don't Refund",
				'deleted' => 'Adult person 1',
			]),
			'operation_at' => now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => now(),
		]);
		DB::table('tour_bookings_master')->where('id', $bookingId)->update(['tourists' => 1, 'total' => 75.00]);
	}

	private function cancelRefundAmount(int $bookingId): float
	{
		return (float) DB::table('transactions')->where('tour_booking_id', $bookingId)->where('status', 'Refund')->latest('id')->value('amount');
	}

	private function setCashFees(string $value): void
	{
		$this->originalCashFees ??= (string) DB::table('settings')->where('key', 'CASH_FEES')->value('value');
		DB::table('settings')->where('key', 'CASH_FEES')->update(['value' => $value]);
	}

	public function test_weather_cancel_of_a_cash_deposit_booking_does_not_refund_a_dont_refund_pax(): void
	{
		$this->setCashFees('0');
		$booking = $this->offlineBooking('Cash', 1);
		$this->removeOfflinePaxWithDontRefund($booking->bookingId, 'Cash', 75.00);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		// Only the remaining pax's $75.00, not the $150.00 originally paid
		$this->assertEqualsWithDelta(75.00, $this->cancelRefundAmount($booking->bookingId), 0.001);
		$this->assertCount(0, $this->stripeRefunds);
	}

	public function test_weather_cancel_of_a_cash_deposit_booking_with_cash_fees_does_not_refund_a_dont_refund_pax(): void
	{
		$this->setCashFees('1');
		$booking = $this->offlineBooking('Cash', 1);
		$this->removeOfflinePaxWithDontRefund($booking->bookingId, 'Cash', 75.00);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		$this->assertEqualsWithDelta(75.00, $this->cancelRefundAmount($booking->bookingId), 0.001);
	}

	public function test_weather_cancel_of_a_paid_in_full_cash_or_invoiced_booking_is_unchanged(): void
	{
		$this->setCashFees('0');

		// Paid in full: refunded from the (already reduced) booking total, as before
		foreach (['Cash', 'Invoiced'] as $via) {
			$booking = $this->offlineBooking($via, 0);
			$this->removeOfflinePaxWithDontRefund($booking->bookingId, $via, 75.00);

			$this->cancelWithWeatherRefund($booking->bookingId);

			$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'), $via);
			$this->assertEqualsWithDelta(75.00, $this->cancelRefundAmount($booking->bookingId), 0.001, $via);

			$booking->cleanup();
			$this->booking = null;
		}
	}

	public function test_weather_cancel_of_a_cash_deposit_booking_without_pax_removal_refunds_everything(): void
	{
		$this->setCashFees('0');
		$booking = $this->offlineBooking('Cash', 1);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertEqualsWithDelta(150.00, $this->cancelRefundAmount($booking->bookingId), 0.001);
	}

	public function test_dont_refund_row_carrying_the_kept_amount_in_amount_is_not_excluded_twice(): void
	{
		$this->setCashFees('0');
		$booking = $this->offlineBooking('Cash', 1);
		// Older rows stored the kept amount in details->amount (already netted by the existing code)
		$this->removeOfflinePaxWithDontRefund($booking->bookingId, 'Cash', 75.00, 75.00);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertEqualsWithDelta(75.00, $this->cancelRefundAmount($booking->bookingId), 0.001);
	}

	public function test_weather_cancel_does_not_refund_a_pax_removed_with_dont_refund(): void
	{
		$booking = $this->cardBooking();
		$this->removePaxWithDontRefund($booking->bookingId, 'Adult person 1', 1);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));

		// Only the remaining pax's half ($85.00 + $6.48) goes back to the card, not $182.96
		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(9148, (int) round($this->stripeRefunds[0]['amount']));

		$refund = DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->where('status', 'Refund')->first();
		$this->assertEqualsWithDelta(91.48, (float) $refund->amount, 0.001);
		// Only the remaining pax's share of the platform fee is recorded as refunded
		$this->assertEqualsWithDelta(6.48, (float) $refund->fee_amount, 0.001);
	}

	public function test_reproduces_booking_d_108878(): void
	{
		// 2 pax paid in full by card: $146.60 tickets + $14.40 fees = $161.00
		$this->booking = (new CreateABooking())->withTourists(2)->withRate(73.30)->withPaymentMethod('Card')->create();
		$id = $this->booking->bookingId;
		DB::table('tour_bookings_master')->where('id', $id)->update(['total' => 161.00, 'booking_total' => 161.00, 'service_commission' => 14.40]);
		DB::table('transactions')->where('id', $this->booking->transactionId)->update(['amount' => 161.00, 'fee_amount' => 14.40, 'payment_type' => 0]);
		DB::table('tour_booking_history')->where('tour_booking_id', $id)->where('operation', 'Payment')->update([
			'ticket_cost' => 146.60,
			'platform_fees' => 14.40,
			'details' => json_encode(['payment_type' => 'Card', 'payment_method' => 'Full Payment', 'amount' => 161]),
		]);

		// Remove Pax with Don't Refund, exactly as stored for D-108878
		$pi = DB::table('transactions')->where('id', $this->booking->transactionId)->value('transaction_number');
		$refundTransactionId = DB::table('transactions')->insertGetId([
			'tour_booking_id' => $id, 'amount' => 0, 'fee_amount' => 0, 'via' => 'Card', 'payment_type' => 0,
			'transaction_number' => $pi, 'response_init' => '{}', 'response_end' => '{}', 'status' => 'Refund',
			'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
		]);
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $id, 'transaction_id' => $refundTransactionId, 'tourists' => 1, 'operation' => 'Refund',
			'payment_type' => 'Card', 'payment_method' => "Don't Refund",
			'details' => json_encode(['amount' => 0, 'refund_keep_us' => 80.5, 'payment_type' => 'Card', 'refund_type' => "Don't Refund", 'deleted' => 'Adult (Ages 8 & Up) person 1', 'fees' => 7.2, 'partner_fees' => 0, 'refund_partner_fees_keep_us' => 0]),
			'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin', 'created_at' => now(),
		]);
		DB::table('tour_bookings_master')->where('id', $id)->update(['tourists' => 1, 'total' => 80.50]);

		$this->cancelWithWeatherRefund($id);

		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(8050, (int) round($this->stripeRefunds[0]['amount']));
		$refund = DB::table('transactions')->where('tour_booking_id', $id)->where('status', 'Refund')->latest('id')->first();
		$this->assertEqualsWithDelta(80.50, (float) $refund->amount, 0.001);
		$this->assertEqualsWithDelta(7.20, (float) $refund->fee_amount, 0.001);
	}

	public function test_weather_cancel_without_pax_removal_still_refunds_the_full_charge(): void
	{
		$booking = $this->cardBooking();

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		$this->assertCount(1, $this->stripeRefunds);
		$this->assertSame(18296, (int) round($this->stripeRefunds[0]['amount']));

		$refund = DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->where('status', 'Refund')->first();
		$this->assertEqualsWithDelta(182.96, (float) $refund->amount, 0.001);
		$this->assertEqualsWithDelta(12.96, (float) $refund->fee_amount, 0.001);
	}

	public function test_weather_cancel_skips_stripe_when_every_paid_pax_was_removed_with_dont_refund(): void
	{
		$booking = $this->cardBooking();
		// "Adult person 2" = two pax in one removal; the booking keeps an unpaid/added pax
		$this->removePaxWithDontRefund($booking->bookingId, 'Adult person 2', 1);

		$this->cancelWithWeatherRefund($booking->bookingId);

		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		// Nothing left to refund — no $0 refund call that Stripe would reject
		$this->assertCount(0, $this->stripeRefunds);
	}
}
