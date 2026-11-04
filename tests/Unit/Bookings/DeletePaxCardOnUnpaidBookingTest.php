<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Removing pax via Edit Booking with payment_type "Card" from a booking that has no payment yet
 * crashed with "Attempt to read property "id" on null": the Card refund block read
 * $transaction->id / ->transaction_number even though there was no charge. With nothing paid
 * there is nothing to refund, so the pax must be removed without any Stripe call.
 */
class DeletePaxCardOnUnpaidBookingTest extends TestCase
{
	private function removeTwoPax(string $refundType): void
	{
		Mail::fake();
		$this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

		$gateway = Mockery::mock(PaymentGatewayInterface::class);
		$gateway->shouldNotReceive('refundPayment');
		$this->app->instance(PaymentGatewayInterface::class, $gateway);

		// 3 pax @ $75, never paid
		$booking = (new CreateABooking())
			->withTourists(3)
			->withRate(75.00)
			->withPaymentMethod('Pay Later')
			->withoutTransaction()
			->create();
		$bookingId = $booking->bookingId;
		$originalTotal = (float) DB::table('tour_bookings_master')->where('id', $bookingId)->value('total');

		try {
			$request = new Request();
			$request->merge([
				'bookingID' => $bookingId,
				'action_type' => 'delete',
				'payment_type' => 'Card',
				'is_cash' => '',
				'refund_Type' => $refundType,
				'check' => ['Adult.0' => $booking->rateId, 'Adult.1' => $booking->rateId],
				'total_tourist' => 3,
				'api' => 1,
			]);

			app(TourBookingRepository::class)->editBooking($request);

			$master = DB::table('tour_bookings_master')->where('id', $bookingId)->first(['tourists', 'total']);
			$this->assertEquals(1, $master->tourists);
			$this->assertLessThan($originalTotal, (float) $master->total, 'Removed pax must come off the booking total');

			$row = DB::table('tour_booking_history')
				->where('tour_booking_id', $bookingId)
				->where('operation', 'Refund')
				->latest('id')
				->first();
			$this->assertNotNull($row, 'Expected a Refund history row for the removal');
			$details = json_decode($row->details, true);
			$this->assertEquals(0, (float) $details['amount'], 'Nothing was paid, so nothing is refunded');
			$this->assertEquals(0, (float) ($details['refund_keep_us'] ?? 0), 'Nothing was paid, so nothing is kept');

			$this->assertSame(0, DB::table('transactions')->where('tour_booking_id', $bookingId)->count(), 'No refund transaction without a payment');
		} finally {
			DB::table('tour_booking_payment_links')->where('order_id', $bookingId)->delete();
			$booking->cleanup();
		}
	}

	public function test_dont_refund_removal_does_not_crash(): void
	{
		$this->removeTwoPax('dont_refund');
	}

	public function test_100_percent_refund_removal_does_not_crash_or_call_stripe(): void
	{
		$this->removeTwoPax('100_refund');
	}

	public function test_ticket_cost_refund_removal_does_not_crash_or_call_stripe(): void
	{
		$this->removeTwoPax('full_refund');
	}
}
