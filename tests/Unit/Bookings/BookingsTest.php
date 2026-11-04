<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Utils\BookingUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingsTest extends TestCase
{
	protected TourBookingRepository $repo;
	protected static array $builders = [];
	protected static array $repoBookingIds = [];

	public function setUp(): void
	{
		parent::setUp();
		$this->repo = app(TourBookingRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		return $admin;
	}

	/**
	 * Test that initBooking returns the expected page structure.
	 */
	public function testCanInitTourBooking(): void
	{
		$this->authAsAdmin();
		$request = new Request();
		$res = $this->repo->initBooking($request);

		$this->assertIsArray($res);
		$this->assertEquals('Book Tour Package', $res['page_title']);
		$this->assertArrayHasKey('tourOperatorId', $res);
		$this->assertArrayHasKey('date', $res);
	}

	/**
	 * Test that a booking created via the fixture exists in the database
	 * and has the correct attributes.
	 */
	public function testBookingFixtureCreatesValidRecord(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(2)
			->withRate(50.00)
			->withCustomer('Fixture Test', 'fixture-' . uniqid() . '@test.com', '5559999999')
			->create();
		static::$builders[] = $builder;

		$booking = TourBooking::find($builder->bookingId);

		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEquals(2, $booking->tourists);
		$this->assertEquals('Fixture Test', $booking->name);
		$this->assertEquals(100.00, (float) $booking->total);

		// Verify transact line item
		$transact = DB::table('tour_bookings_transact')
			->where('tour_booking_id', $builder->bookingId)
			->first();
		$this->assertNotNull($transact);
		$this->assertEquals(2, $transact->tourists);
		$this->assertEquals($builder->rateId, $transact->tour_package_rate_id);

		// Verify transaction
		$transaction = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Completed')
			->first();
		$this->assertNotNull($transaction);
		$this->assertEquals(100.00, (float) $transaction->amount);

		// Verify history
		$history = DB::table('tour_booking_history')
			->where('tour_booking_id', $builder->bookingId)
			->where('operation', 'Payment')
			->first();
		$this->assertNotNull($history);
	}

	/**
	 * Test cancelling a booking without refund changes status to Cancelled
	 * and preserves the original transaction amount.
	 */
	public function testCanCancelBooking(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'cancel_booking' => 1,
			'refund_type' => 'dont_refund',
			'partial_refund_value' => '',
			'comment' => 'Test cancel from unit test',
			'api' => 1,
		]);

		$this->repo->cancel($request, $builder->bookingId);

		$booking = TourBooking::find($builder->bookingId);
		$this->assertEquals('Cancelled', $booking->status);
		$this->assertEquals(50.00, (float) $booking->total, 'Total should be unchanged after cancel without refund');

		// Original completed transaction should remain intact
		$completedTxn = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Completed')
			->first();
		$this->assertNotNull($completedTxn);
		$this->assertEquals(50.00, (float) $completedTxn->amount, 'Original payment amount should be preserved');

		// DontRefundCancelService creates a $0 refund transaction for bookkeeping
		$refundTxn = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Refund')
			->first();
		$this->assertNotNull($refundTxn, 'Cancel should create a refund transaction record');
		$this->assertEquals(0.00, (float) $refundTxn->amount, 'Dont-refund cancel should have $0 refund amount');

		// Verify cancellation history was recorded
		$history = DB::table('tour_booking_history')
			->where('tour_booking_id', $builder->bookingId)
			->where('operation', 'Cancelled')
			->first();
		$this->assertNotNull($history, 'Cancellation should be recorded in history');
	}

	/**
	 * Test restoring a cancelled booking sets status back to Booked.
	 */
	public function testCanRestoreCancelledBooking(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->create();
		static::$builders[] = $builder;

		// First cancel
		$cancelRequest = new Request();
		$cancelRequest->merge([
			'cancel_booking' => 1,
			'refund_type' => 'dont_refund',
			'partial_refund_value' => '',
			'comment' => 'Cancel before restore test',
			'api' => 1,
		]);
		$this->repo->cancel($cancelRequest, $builder->bookingId);

		$booking = TourBooking::find($builder->bookingId);
		$this->assertEquals('Cancelled', $booking->status);

		// Then restore
		$restoreRequest = new Request();
		$this->repo->restoreBooking($restoreRequest, $builder->bookingId);

		$booking->refresh();
		$this->assertEquals('Booked', $booking->status);

		// Verify restore history
		$history = DB::table('tour_booking_history')
			->where('tour_booking_id', $builder->bookingId)
			->where('operation', 'Restore')
			->first();
		$this->assertNotNull($history, 'Restore should be recorded in history');
	}

	/**
	 * Test refunding a cash booking creates a refund transaction with the
	 * correct dollar amount and preserves the original payment.
	 */
	public function testCanRefundCashBooking(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->withPaymentMethod('Cash')
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'refund_booking' => 1,
			'refund_type' => 'partial_refund',
			'refund_type_percentage' => 'Percentage',
			'percentage_refund_value' => 50,
			'partial_refund_value' => 25.00,
			'is_cash' => 'Cash',
			'comment' => 'Test partial refund',
			'api' => 1,
		]);

		$this->repo->refund($request, $builder->bookingId);

		// Booking should remain Booked after a partial refund
		$booking = TourBooking::find($builder->bookingId);
		$this->assertEquals('Booked', $booking->status);

		// Verify refund transaction has the correct amount
		$refundTxn = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Refund')
			->first();
		$this->assertNotNull($refundTxn, 'Refund transaction should exist');
		$this->assertEquals(25.00, (float) $refundTxn->amount, 'Refund amount should be $25');

		// Original completed transaction should be unchanged
		$completedTxn = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Completed')
			->first();
		$this->assertNotNull($completedTxn);
		$this->assertEquals(50.00, (float) $completedTxn->amount, 'Original payment should be unchanged');

		// Verify refund history with correct details
		$history = DB::table('tour_booking_history')
			->where('tour_booking_id', $builder->bookingId)
			->where('operation', 'Refund')
			->first();
		$this->assertNotNull($history, 'Refund should be recorded in history');
		$details = json_decode($history->details, true);
		$this->assertEquals(25.00, (float) $details['amount'], 'History should record $25 refund amount');
	}

	/**
	 * Test that cancelling with full refund on a cash booking creates a
	 * refund transaction matching the original payment amount.
	 */
	public function testCanCancelWithFullRefund(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->withPaymentMethod('Cash')
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'cancel_booking' => 1,
			'refund_type' => 'full_refund',
			'partial_refund_value' => 50.00,
			'comment' => 'Full refund cancel test',
			'api' => 1,
		]);

		$this->repo->cancel($request, $builder->bookingId);

		$booking = TourBooking::find($builder->bookingId);
		$this->assertEquals('Cancelled', $booking->status);

		// Verify refund transaction amount matches original payment
		$refundTxn = DB::table('transactions')
			->where('tour_booking_id', $builder->bookingId)
			->where('status', 'Refund')
			->first();
		$this->assertNotNull($refundTxn, 'Full refund should create a refund transaction');
		$this->assertEquals(50.00, (float) $refundTxn->amount, 'Refund amount should equal original $50 payment');
	}

	/**
	 * Test that attempting to refund more than the paid amount throws an exception.
	 */
	public function testCannotRefundMoreThanPaidAmount(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->withPaymentMethod('Cash')
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'refund_booking' => 1,
			'refund_type' => 'partial_refund',
			'refund_type_percentage' => 'Dollar',
			'partial_refund_value' => 100.00,  // More than the $50 paid
			'is_cash' => 'Cash',
			'comment' => 'Over-refund attempt',
			'api' => 1,
		]);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('less than or equal');
		$this->repo->refund($request, $builder->bookingId);
	}

	/**
	 * Test that attempting to cancel a booking which has already been
	 * linked to an invoice (invoice_bookings row exists) throws an
	 * exception before any cancellation mutation occurs.
	 */
	public function testCannotCancelInvoicedBooking(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(1)
			->withRate(50.00)
			->create();
		static::$builders[] = $builder;

		$tourOperatorId = DB::table('tour_operators')->value('id') ?? 1;
		$today = Carbon::today()->toDateString();

		$invoiceId = DB::table('invoices')->insertGetId([
			'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
			'tour_operator_id' => $tourOperatorId,
			'customer_id' => $builder->customerId,
			'invoice_number' => 'NATTEST' . uniqid(),
			'from_date' => $today,
			'to_date' => $today,
			'due_date' => Carbon::today()->addDays(30)->toDateString(),
			'amount' => 50.00,
			'status' => 'draft',
			'email_sent' => 0,
			'created_by' => 1,
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]);

		try {
			DB::table('invoice_bookings')->insert([
				'tour_booking_id' => $builder->bookingId,
				'invoice_id' => $invoiceId,
				'created_by' => 1,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			]);

			$request = new Request();
			$request->merge([
				'cancel_booking' => 1,
				'refund_type' => 'dont_refund',
				'partial_refund_value' => '',
				'comment' => 'Attempt to cancel an invoiced booking',
				'api' => 1,
			]);

			try {
				$this->repo->cancel($request, $builder->bookingId);
				$this->fail('Expected an exception when cancelling an invoiced booking.');
			} catch (\Exception $e) {
				$this->assertStringContainsString('invoiced', $e->getMessage());
			}

			// The guard must fire before any mutation -- booking stays untouched.
			$booking = TourBooking::find($builder->bookingId);
			$this->assertEquals('Booked', $booking->status, 'Cancelling an invoiced booking must not change its status');
		} finally {
			DB::table('invoice_bookings')->where('invoice_id', $invoiceId)->delete();
			DB::table('invoices')->where('id', $invoiceId)->delete();
		}
	}

	/**
	 * Test creating a booking through the repository createBooking() method.
	 * Exercises the full service layer: slot validation, customer creation,
	 * price calculation, transaction recording, and history.
	 *
	 * @dataProvider createBookingRateProvider
	 */
	public function testCreateBookingViaRepository(
		int    $cashPriceEnabled,
		float  $cardRate,
		float  $cashRate,
		bool   $isCashRate,
		string $paymentMethod,
		int    $touristCount,
	): void {
		$this->authAsAdmin();

		$effectiveRate = ($cashPriceEnabled && $isCashRate && $cashRate > 0) ? $cashRate : $cardRate;
		$expectedTotal = $touristCount * $effectiveRate;
		$tomorrow      = Carbon::tomorrow()->toDateString();
		
		$this->withSetting('CASH_PRICE', $cashPriceEnabled, function () use (
			$tomorrow,
			$cardRate,
			$cashRate,
			$isCashRate,
			$touristCount,
			$paymentMethod,
			$expectedTotal,
			$effectiveRate,
			&$bookingId
		) {
			$builder = (new CreateABooking())
				->withDate($tomorrow)
				->withTourists(1)
				->withRate($cardRate)
				->withSeats(20)
				->withoutTransaction()
				->withoutHistory()
				->withStatus('Booked');
			if ($isCashRate) {
				$builder->withCashRate($cashRate);
			}
			$setup = $builder->create();
			static::$builders[] = $setup;

			$request = new Request();
			$request->merge([
				'payment_method'       => $paymentMethod,
				'tourists'             => [$touristCount],
				'rate_group'           => [$setup->rateId],
				'permit_fee'           => [null, 0],
				'slot_time'            => $setup->slotTimeId,
				'affiliatecust'        => 0,
				'customer_email'       => 'createbooking-test-' . uniqid() . '@test.com',
				'customer_name'        => 'Repo Create Test',
				'customer_phone_number' => '5551230000',
				'customer_comment'     => '',
				'subtt'                => $expectedTotal,
				'fee'                  => 0,
				'custom_fields'        => '',
				'custom_charge_data'   => '',
				'custom_charge_fees'   => 0,
				'pax_addons_data'      => '',
				'addons_data'          => '',
				'tip_data'             => '',
				'dividedBy'            => 'Please select option',
				'tour_operator_note'   => '',
				'hotel_id'             => 0,
				'addons'               => 0,
				'split_value'          => '',
				'booked_by_staff'      => '',
			]);

			$result = $this->repo->createBooking($request);

			$this->assertIsArray($result);
			$this->assertArrayHasKey('bookingId', $result);
			$bookingId = $result['bookingId'];
			static::$repoBookingIds[] = $bookingId;

			// Booking record
			$booking = TourBooking::find($bookingId);
			$this->assertNotNull($booking);
			$this->assertEquals('Booked', $booking->status);
			$this->assertEquals($touristCount, $booking->tourists);
			$this->assertEquals($expectedTotal, (float) $booking->total);

			// Transact — rate and cash flag
			$transact = DB::table('tour_bookings_transact')
				->where('tour_booking_id', $bookingId)
				->first();
			$this->assertNotNull($transact);
			$this->assertEquals($touristCount, $transact->tourists);
			$this->assertEquals($setup->rateId, $transact->tour_package_rate_id);
			$this->assertEquals(
				$isCashRate ? 1 : 0,
				(int) $transact->is_cash_rate,
				'is_cash_rate flag should match the scenario'
			);
			$this->assertEquals(
				$effectiveRate,
				(float) $transact->rate,
				'Transact rate should use cash_rate when cash pricing is active'
			);

			// Transaction
			$transaction = DB::table('transactions')
				->where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->first();
			$this->assertNotNull($transaction);
			$this->assertEquals($expectedTotal, (float) $transaction->amount);

			// History
			$history = DB::table('tour_booking_history')
				->where('tour_booking_id', $bookingId)
				->where('operation', 'Payment')
				->first();
			$this->assertNotNull($history);
		});
	}

	/**
	 * The server is the source of truth for permit fees. An affiliate (or any
	 * front-end user on the affiliate guard) cannot inject a permit fee — the
	 * rate's value is always enforced. A tour operator/admin may still waive it.
	 */
	public function testAffiliateCannotInjectPermitFee(): void
	{
		$this->authAsAdmin();

		// Rate whose real permit fee is 15.00
		$setup = (new CreateABooking())
			->withDate(Carbon::tomorrow()->toDateString())
			->withTourists(1)
			->withRate(50)
			->withPermitFee(15.00)
			->withSeats(20)
			->withoutTransaction()
			->withoutHistory()
			->create();
		static::$builders[] = $setup;

		$request = new Request();
		$request->merge([
			'tourists'   => [2],
			'rate_group' => [$setup->rateId],
			'permit_fee' => [8.00], // client attempts to inject a wrong permit fee
		]);
		$tourBooking = (object) ['tourists' => 0];

		// Affiliate: the posted 8.00 is ignored; the rate's 15.00 is enforced.
		session(['guard' => 'affiliate']);
		$affiliate = BookingUtils::calculateChangedTouristsData($request, $tourBooking, '0', []);
		$this->assertEquals(
			15.00,
			(float) $affiliate['permitfeeAr'][$setup->rateId],
			'Affiliate must not be able to set the permit fee; the rate value is enforced'
		);

		// Tour operator / admin: the posted value is respected (they may waive the fee).
		session(['guard' => 'admin']);
		$request->merge(['permit_fee' => [0]]);
		$staff = BookingUtils::calculateChangedTouristsData($request, $tourBooking, '0', []);
		$this->assertEquals(
			0.00,
			(float) $staff['permitfeeAr'][$setup->rateId],
			'Tour operator/admin may waive the permit fee'
		);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];

		// Bookings created by the repo (testCreateBookingViaRepository) are
		// not tracked by a CreateABooking builder, so clean them up by ID.
		DB::statement('SET FOREIGN_KEY_CHECKS=0');
		foreach (static::$repoBookingIds as $bookingId) {
			$booking = DB::table('tour_bookings_master')->where('id', $bookingId)->first();
			DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
			DB::table('transactions')->where('tour_booking_id', $bookingId)->delete();
			DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->delete();
			if ($booking) {
				try {
					DB::table('tour_slot_summary')->where('tour_slot_id', $booking->tour_slot_id)->delete();
				} catch (\Exception $e) {
				}
				DB::table('tour_bookings_master')->where('id', $bookingId)->delete();
				DB::table('customers')->where('id', $booking->customer_id)->delete();
			}
		}
		DB::statement('SET FOREIGN_KEY_CHECKS=1');
		static::$repoBookingIds = [];
	}
	// ---------------------------------------------------------------
	// Helper: temporarily flip a settings key for one test
	// ---------------------------------------------------------------
	private function withSetting(string $key, $value, callable $callback): void
	{
		$existing = DB::table('settings')->where('key', $key)->first();
		$original = $existing?->value;

		DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
		try {
			$callback();
		} finally {
			if ($existing) {
				DB::table('settings')->where('key', $key)->update(['value' => $original]);
			} else {
				DB::table('settings')->where('key', $key)->delete();
			}
		}
	}
	// ---------------------------------------------------------------
	// Data provider
	// ---------------------------------------------------------------
	public static function createBookingRateProvider(): array
	{
		return [
			'normal_rate' => [
				'cashPriceEnabled' => 0,
				'cardRate'         => 50.00,
				'cashRate'         => 0.00,
				'isCashRate'       => false,
				'paymentMethod'    => 'Cash',
				'touristCount'     => 2,
			],
			'cash_rate' => [
				'cashPriceEnabled' => 1,
				'cardRate'         => 50.00,
				'cashRate'         => 40.00,
				'isCashRate'       => true,
				'paymentMethod'    => 'Cash',
				'touristCount'     => 2,
			],
		];
	}
}
