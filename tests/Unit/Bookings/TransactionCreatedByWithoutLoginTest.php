<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Services\Booking\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * transactions.created_by is NOT NULL, but the add-on / ticket / custom-charge refund writers
 * stored a bare Auth::id(). A customer cancelling from the public cancel link has no login, so
 * the insert crashed — after Stripe had already refunded, leaving that refund unrecorded. With no
 * login the row is now attributed to the booking's customer (as 'Customer'), else 0; a logged-in
 * user is recorded exactly as before.
 */
class TransactionCreatedByWithoutLoginTest extends TestCase
{
	private ?CreateABooking $booking = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->booking = (new CreateABooking())->withTourists(1)->withRate(50.00)->withPaymentMethod('Card')->create();
	}

	protected function tearDown(): void
	{
		$this->booking?->cleanup();

		parent::tearDown();
	}

	/**
	 * Write one refund row through each writer that used a bare Auth::id().
	 *
	 * @return array<string, object> transaction rows keyed by writer
	 */
	private function writeRefunds(): array
	{
		$id = $this->booking->bookingId;
		$booking = DB::table('tour_bookings_master')->where('id', $id)->first();

		$ids = [
			'addon' => TransactionService::storeAddonsRefundInTransaction(
				$booking,
				(object) ['payment_method' => 'Card'],
				['price' => 10.00, 'fee' => 0, 'transaction_id' => null, 'application_fee_id' => null]
			),
			'ticket' => TransactionService::storeRefundInTransaction($id, 20.00, 0, 'Card'),
			'custom_charge' => TransactionService::storeCustomChargeRefundInTransaction($id, 5.00, 0, 'Card'),
		];

		return array_map(fn ($txId) => DB::table('transactions')->where('id', $txId)->first(['created_by', 'created_by_user_type']), $ids);
	}

	public function test_without_login_the_refund_is_recorded_against_the_bookings_customer(): void
	{
		Auth::forgetGuards();

		foreach ($this->writeRefunds() as $writer => $row) {
			$this->assertSame($this->booking->customerId, (int) $row->created_by, $writer);
			$this->assertSame('Customer', $row->created_by_user_type, $writer);
		}
	}

	public function test_without_login_or_a_matching_customer_the_refund_is_still_recorded(): void
	{
		Auth::forgetGuards();
		DB::table('tour_bookings_master')->where('id', $this->booking->bookingId)->update(['email' => 'no-such-customer-' . uniqid() . '@test.com']);

		foreach ($this->writeRefunds() as $writer => $row) {
			$this->assertSame(0, (int) $row->created_by, $writer);
			$this->assertSame('Customer', $row->created_by_user_type, $writer);
		}
	}

	public function test_a_logged_in_admin_is_recorded_as_before(): void
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');

		foreach ($this->writeRefunds() as $writer => $row) {
			$this->assertSame((int) $admin->id, (int) $row->created_by, $writer);
			$this->assertSame('Admin', $row->created_by_user_type, $writer);
		}
	}
}
