<?php

namespace Tests\Unit\SwitchPackage;

use App\Models\Admin;
use App\Models\TourBooking;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\SwitchTourPackage\SwitchTourPackageCashService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class SwitchPackageTest extends TestCase
{
	protected TourBookingRepository $repo;
	protected static array $builders = [];

	public function setUp(): void
	{
		parent::setUp();
		$this->repo = app(TourBookingRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Create a booking on a far-future date so it does not collide with real data.
	 */
	protected function createBooking(float $rate, int $tourists = 1, string $paymentMethod = 'Cash'): CreateABooking
	{
		$date = Carbon::today()->addDays(200)->toDateString();
		$builder = (new CreateABooking())
			->withDate($date)
			->withTourists($tourists)
			->withRate($rate)
			->withPaymentMethod($paymentMethod)
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	/**
	 * Create the destination package/slot infrastructure (no booking transaction or history needed).
	 */
	protected function createDestination(float $rate): CreateABooking
	{
		$date = Carbon::today()->addDays(200)->toDateString();
		$builder = (new CreateABooking())
			->withDate($date)
			->withRate($rate)
			->withNewPackage()
			->withoutTransaction()
			->withoutHistory()
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	/**
	 * Build a switch-package request.
	 */
	protected function buildSwitchRequest(int $bookingId, int $newPackageId, int $newSlotTimeId, array $extra = []): Request
	{
		$request = new Request();
		$request->merge(array_merge([
			'bookingID' => $bookingId,
			'tour_package_switch' => $newPackageId,
			'tour_slot_time_switch' => $newSlotTimeId,
			'validation_check' => 'false',
			'payment_method' => 'Cash',
		], $extra));
		return $request;
	}

	/**
	 * Test switching to a higher-priced package (payment case).
	 *
	 * Creates a $50 booking, switches to a $75 package.
	 * Verifies the booking is updated to the new package/slot and a
	 * 'Swipe Tour Package' history entry is created.
	 */
	public function testSwitchPackageUpgradePayment(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(50.00, 2);
		$destination = $this->createDestination(75.00);

		$request = $this->buildSwitchRequest(
			$original->bookingId,
			$destination->packageId,
			$destination->slotTimeId
		);

		$result = $this->repo->switchPackageBooking($request);

		// Return value should contain the booking ID and encrypted token
		$this->assertIsArray($result);
		$this->assertArrayHasKey('bookingId', $result);
		$this->assertArrayHasKey('bookingToken', $result);
		$this->assertEquals($original->bookingId, $result['bookingId']);

		// Booking record should now point to the new package and slot
		$booking = TourBooking::find($original->bookingId);
		$this->assertEquals($destination->packageId, $booking->tour_package_id);
		$this->assertEquals($destination->slotTimeId, $booking->tour_slot_id);

		// Booking total should have increased (new rate is higher)
		$this->assertGreaterThan(100.00, (float) $booking->total, 'Total should exceed original $100');

		// Old transact records should be soft-deleted
		$oldTransact = DB::table('tour_bookings_transact')
			->where('tour_booking_id', $original->bookingId)
			->where('tour_package_rate_id', $original->rateId)
			->first();
		$this->assertNotNull($oldTransact->deleted_at, 'Old transact should be soft-deleted');

		// New transact records should exist (not soft-deleted, different from original)
		$newTransact = DB::table('tour_bookings_transact')
			->where('tour_booking_id', $original->bookingId)
			->where('id', '!=', $original->transactId)
			->where('deleted_by', 0)
			->first();
		$this->assertNotNull($newTransact, 'New transact record should exist');

		// Swipe history should be recorded
		$swipeHistory = DB::table('tour_booking_package_swipe_history')
			->where('tour_booking_id', $original->bookingId)
			->first();
		$this->assertNotNull($swipeHistory, 'Swipe history should be created');
		$this->assertEquals($original->packageId, $swipeHistory->old_tour_package_id);
		$this->assertEquals($destination->packageId, $swipeHistory->new_tour_package_id);

		// A 'Switch Tour Package' operation should exist in booking history
		$historyEntry = DB::table('tour_booking_history')
			->where('tour_booking_id', $original->bookingId)
			->where('operation', 'Switch Tour Package')
			->first();
		$this->assertNotNull($historyEntry, 'Switch Tour Package history should exist');
	}

	/**
	 * Test switching to a lower-priced package (refund case).
	 *
	 * Creates a $100 booking, switches to a $60 package with 100% refund.
	 * Verifies the booking is updated and a refund transaction is created.
	 */
	public function testSwitchPackageDowngradeRefund(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(100.00, 1);
		$destination = $this->createDestination(60.00);

		$request = $this->buildSwitchRequest(
			$original->bookingId,
			$destination->packageId,
			$destination->slotTimeId,
			[
				'refund_type' => '100_refund',
				'is_cash' => 'Cash',
			]
		);

		$result = $this->repo->switchPackageBooking($request);

		$this->assertIsArray($result);
		$this->assertEquals($original->bookingId, $result['bookingId']);

		// Booking should point to the new package
		$booking = TourBooking::find($original->bookingId);
		$this->assertEquals($destination->packageId, $booking->tour_package_id);
		$this->assertEquals($destination->slotTimeId, $booking->tour_slot_id);

		// Booking total should have decreased
		$this->assertLessThan(100.00, (float) $booking->total, 'Total should be less than original $100');

		// A refund transaction should exist
		$refundTrans = DB::table('transactions')
			->where('tour_booking_id', $original->bookingId)
			->where('status', 'Refund')
			->first();
		$this->assertNotNull($refundTrans, 'A refund transaction should be created');
		$this->assertGreaterThan(0, (float) $refundTrans->amount, 'Refund amount should be positive');

		// A Refund history entry should exist
		$refundHistory = DB::table('tour_booking_history')
			->where('tour_booking_id', $original->bookingId)
			->where('operation', 'Refund')
			->first();
		$this->assertNotNull($refundHistory, 'Refund history should be created');
	}

	/**
	 * Test that validation_check=true returns payment info without modifying the booking.
	 *
	 * When validation_check is 'true', the service should return a payment/refund
	 * indicator without actually performing the switch.
	 */
	public function testValidationCheckReturnsPaymentInfoOnly(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(50.00, 1);
		$destination = $this->createDestination(80.00);

		$request = $this->buildSwitchRequest(
			$original->bookingId,
			$destination->packageId,
			$destination->slotTimeId,
			['validation_check' => 'true']
		);

		$result = $this->repo->switchPackageBooking($request);

		// Validation check should return payment indicator
		$this->assertIsArray($result);
		$this->assertArrayHasKey('payment', $result);
		$this->assertEquals(1, $result['payment'], 'Should indicate payment needed for upgrade');
		$this->assertArrayHasKey('amount', $result);
		$this->assertGreaterThan(0, $result['amount']);

		// Booking should NOT have changed
		$booking = TourBooking::find($original->bookingId);
		$this->assertEquals($original->packageId, $booking->tour_package_id, 'Package should be unchanged');
		$this->assertEquals($original->slotTimeId, $booking->tour_slot_id, 'Slot should be unchanged');
	}

	/**
	 * Test that tourist count and booking status are preserved after a switch.
	 *
	 * Creates a 3-tourist booking and switches to a new package.
	 * Verifies the tourist count and status remain unchanged.
	 */
	public function testSwitchPackagePreservesTouristCountAndStatus(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(50.00, 3);
		$destination = $this->createDestination(65.00);

		$request = $this->buildSwitchRequest(
			$original->bookingId,
			$destination->packageId,
			$destination->slotTimeId
		);

		$result = $this->repo->switchPackageBooking($request);

		$this->assertIsArray($result);
		$this->assertEquals($original->bookingId, $result['bookingId']);

		$booking = TourBooking::find($original->bookingId);
		$this->assertEquals($destination->packageId, $booking->tour_package_id);
		$this->assertEquals(3, $booking->tourists, 'Tourist count should be preserved');
		$this->assertEquals('Booked', $booking->status, 'Booking status should be preserved');
	}

	/**
	 * isSwitchPackageDisabled() used to unconditionally block Switch whenever a booking had
	 * more than one ticket-level payment transaction or any refund transaction — which is
	 * exactly what a "Change People" pax addition/removal creates, even with zero add-ons.
	 * Add-ons and an already-switched booking must still unconditionally block it.
	 */
	public function testIsSwitchPackageDisabledAllowsModifiedBookingWithoutAddons(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(50.00, 1);
		$bookingM = TourBooking::find($original->bookingId);

		$noAddons = ['addons_payment_price' => 0];
		$noPayLaterAddons = ['addons_price' => 0];

		// Two ticket-level payments (e.g. the original booking + a later "Change People" pax
		// addition) and a refund transaction (e.g. a pax removal) — this alone must no longer
		// disable Switch.
		$this->assertFalse(
			isSwitchPackageDisabled($bookingM, null, [1, 2], [1], 0, $noAddons, $noPayLaterAddons),
			'Switch should stay enabled for a modified-but-addon-free booking'
		);

		// Add-ons must still unconditionally block it, regardless of payment/refund counts.
		$withAddons = ['addons_payment_price' => 25.00];
		$this->assertTrue(
			isSwitchPackageDisabled($bookingM, null, [1, 2], [1], 0, $withAddons, $noPayLaterAddons),
			'Switch must stay blocked whenever the booking has priced add-ons'
		);

		// A booking that has already been switched once must still be blocked too.
		$this->assertTrue(
			isSwitchPackageDisabled($bookingM, null, [1], [], 2, $noAddons, $noPayLaterAddons),
			'Switch must stay blocked once the booking has already been switched'
		);
	}

	/**
	 * calculateRemainingAmount() used to query tour_booking_history's Payment/Refund rows
	 * with no charge_type filter at all, so a paid custom charge's history row got folded
	 * into the ticket-cost pool used to compute a switch's charge/refund delta — silently
	 * wrong money math for any booking with a custom charge in its history, independent of
	 * add-ons or pax changes. It must now only count genuine ticket/pax activity
	 * (charge_type NULL), matching the $trans/$paymentTransCount queries in the same method.
	 */
	public function testCalculateRemainingAmountExcludesCustomChargeHistory(): void
	{
		$this->authAsAdmin();

		$original = $this->createBooking(100.00, 1);

		// A paid custom charge on this booking — real money, but not part of the ticket cost
		// the switch should be reconciling against.
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $original->bookingId,
			'operation' => 'Payment',
			'charge_type' => 'custom_charge',
			'ticket_cost' => 999.99,
			'discount' => 0,
			'permit_fees' => 0,
			'processing_fees' => 0,
			'payment_type' => 'Cash',
			'payment_method' => 'Full Payment',
			'details' => json_encode([
				'custom_charge' => ['amount' => 999.99, 'taxable' => 1, 'status' => 'Paid'],
				'amount' => 999.99,
			]),
			'operation_at' => Carbon::now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => Carbon::now(),
		]);

		$result = SwitchTourPackageCashService::calculateRemainingAmount($original->bookingId, '100_refund');

		// Only the real $100 ticket payment should count — the $999.99 custom charge must
		// not leak into the ticket-cost pool. (Asserted on ticketCostAmount specifically:
		// it's computed the same way regardless of the cashFees()/payment-type branching
		// elsewhere in this method, so this doesn't depend on tenant cash-fee settings.)
		$this->assertEquals(
			100.00,
			(float) $result['ticketCostAmount'],
			'Custom charge history must not be folded into the ticket-cost pool'
		);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
