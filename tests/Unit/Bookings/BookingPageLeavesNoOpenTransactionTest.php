<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;
use Throwable;

/**
 * TourBookingRepository::booking() (booking page / print / receipt data) is read-only, but it
 * opened a DB transaction it never committed. Every write later in the same request then ran
 * inside it and was silently rolled back when the connection closed. It must leave the
 * transaction level exactly as it found it — including on error, where a stray rollBack()
 * without its own beginTransaction() would roll back the caller's transaction.
 */
class BookingPageLeavesNoOpenTransactionTest extends TestCase
{
	public function test_loading_a_booking_leaves_no_transaction_open(): void
	{
		$this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');
		$booking = (new CreateABooking())->withTourists(2)->withRate(50.00)->withPaymentMethod('Card')->create();

		try {
			$repo = app(TourBookingRepository::class);
			foreach ([false, true] as $forPrinting) {
				$before = DB::transactionLevel();
				$vars = $repo->booking($booking->bookingId, $forPrinting);

				$this->assertNotEmpty($vars);
				$this->assertSame($before, DB::transactionLevel(), 'booking() must not leave a transaction open (forPrinting=' . (int) $forPrinting . ')');
			}
		} finally {
			while (DB::transactionLevel() > 0) {
				DB::rollBack();
			}
			$booking->cleanup();
		}
	}

	public function test_a_failing_load_does_not_roll_back_the_callers_transaction(): void
	{
		$this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

		DB::beginTransaction();
		try {
			try {
				app(TourBookingRepository::class)->booking(PHP_INT_MAX, false);
				$this->fail('Loading a booking that does not exist must throw');
			} catch (Throwable) {
				// expected
			}

			$this->assertSame(1, DB::transactionLevel(), "booking()'s error path must not roll back the caller's transaction");
		} finally {
			while (DB::transactionLevel() > 0) {
				DB::rollBack();
			}
		}
	}
}
