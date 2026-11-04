<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Removing pax with "Don't Refund" from a booking that has no payment yet zeroed details->amount
 * but still stored the removed pax's full price in refund_keep_us, as if it had been kept. Nothing
 * was ever charged, so nothing is kept: refund_keep_us must be 0 (it is read as kept money by
 * cancel refunds and by the booking page / print totals).
 */
class DontRefundPaxRemovalOnUnpaidBookingKeepsNothingTest extends TestCase
{
	private function removePaxWithDontRefund(string $paymentType): void
	{
		Mail::fake();
		$this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

		$booking = (new CreateABooking())
			->withTourists(3)
			->withRate(75.00)
			->withPaymentMethod('Pay Later')
			->withoutTransaction()
			->create();
		$bookingId = $booking->bookingId;

		try {
			$request = new Request();
			$request->merge([
				'bookingID' => $bookingId,
				'action_type' => 'delete',
				'payment_type' => $paymentType,
				'is_cash' => '',
				'refund_Type' => 'dont_refund',
				'check' => ['Adult.0' => $booking->rateId, 'Adult.1' => $booking->rateId],
				'total_tourist' => 3,
				'api' => 1,
			]);

			app(TourBookingRepository::class)->editBooking($request);

			$this->assertEquals(1, DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'));

			$row = DB::table('tour_booking_history')
				->where('tour_booking_id', $bookingId)
				->where('operation', 'Refund')
				->where('payment_method', "Don't Refund")
				->latest('id')
				->first();
			$this->assertNotNull($row, "Expected a Don't Refund history row");
			$details = json_decode($row->details, true);
			$this->assertEquals(0, (float) $details['amount']);
			$this->assertEquals(0, (float) $details['refund_keep_us'], 'Nothing was paid, so nothing is kept');
		} finally {
			DB::table('tour_booking_payment_links')->where('order_id', $bookingId)->delete();
			$booking->cleanup();
		}
	}

	public function test_unpaid_soft_block_booking_keeps_nothing(): void
	{
		// Soft block / unpaid booking: Remove Pax sends an empty payment_type
		$this->removePaxWithDontRefund('');
	}
}
