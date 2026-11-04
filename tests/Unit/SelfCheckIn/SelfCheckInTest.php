<?php

namespace Tests\Unit\SelfCheckIn;

use App\Models\Admin;
use App\Repositories\CheckIn\CheckInRepository;
use App\Utils\BookingUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class SelfCheckInTest extends TestCase
{
	protected static array $builders = [];
	protected static array $historyIds = [];

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Test that updateHistory creates a check-in history record
	 * with the correct booking ID, operation, and details.
	 */
	public function testUpdateHistoryCreatesCheckInRecord(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->create();
		static::$builders[] = $builder;

		$details = json_encode([
			'status' => '1',
			'who' => 'unit test',
			'created_by_with_name' => 'unit test',
		]);

		$result = BookingUtils::updateHistory($builder->bookingId, $details);

		$this->assertTrue($result['success']);
		$this->assertNull($result['error']);
		$this->assertNotNull($result['result']);

		$historyRow = $result['result'];
		static::$historyIds[] = $historyRow->id;

		$this->assertEquals($builder->bookingId, $historyRow->tour_booking_id);
		$this->assertEquals('Check-in', $historyRow->operation);

		$decoded = json_decode($historyRow->details, true);
		$this->assertEquals('1', $decoded['status']);
		$this->assertEquals('unit test', $decoded['who']);
	}

	/**
	 * Test that getBookingCustomer returns the customer linked to a booking,
	 * including the customer name and the booking_id field.
	 */
	public function testGetBookingCustomerReturnsCustomerData(): void
	{
		$builder = (new CreateABooking())
			->withCustomer('CheckIn Test Customer', 'checkin-' . uniqid() . '@test.com', '5550001234')
			->create();
		static::$builders[] = $builder;

		$customer = BookingUtils::getBookingCustomer($builder->bookingId);

		$this->assertNotNull($customer);
		$this->assertObjectHasProperty('name', $customer);
		$this->assertObjectHasProperty('booking_id', $customer);
		$this->assertObjectHasProperty('email', $customer);
		$this->assertEquals('CheckIn Test Customer', $customer->name);
		$this->assertEquals($builder->bookingId, $customer->booking_id);
	}

	/**
	 * Test that getBookingCustomer returns null for a non-existent booking ID.
	 */
	public function testGetBookingCustomerReturnsNullForInvalidId(): void
	{
		$result = BookingUtils::getBookingCustomer(999999999);
		$this->assertNull($result);
	}

	/**
	 * Test that getTouristCount returns the correct number of tourists
	 * for a booking created with a known tourist count.
	 */
	public function testGetTouristCountMatchesBooking(): void
	{
		$builder = (new CreateABooking())
			->withTourists(4)
			->create();
		static::$builders[] = $builder;

		$count = BookingUtils::getTouristCount($builder->bookingId);

		$this->assertEquals(4, (int) $count);
	}

	/**
	 * Test that validateBooking returns no error for a valid, active booking
	 * that is happening within the next hour.
	 */
	public function testValidateBookingReturnsNoErrorForValidBooking(): void
	{
		$booking = (object) [
			'status' => 'Booked',
			'deleted_by' => 0,
			'tour_d_t' => Carbon::now()->addMinutes(30)->toDateTimeString(),
		];

		$result = BookingUtils::validateBooking($booking);

		$this->assertNull($result['code']);
		$this->assertNull($result['error']);
		$this->assertArrayHasKey('time_delta', $result);
	}

	/**
	 * Test that validateBooking detects a cancelled booking.
	 */
	public function testValidateBookingDetectsCancelledStatus(): void
	{
		$booking = (object) [
			'status' => 'Cancelled',
			'deleted_by' => 0,
			'tour_d_t' => Carbon::now()->toDateTimeString(),
		];

		$result = BookingUtils::validateBooking($booking);

		$this->assertEquals('cancelled', $result['code']);
		$this->assertNotNull($result['error']);
		$this->assertEquals('This Tour has been cancelled', $result['error']['error']);
	}

	/**
	 * Test that validateBooking returns 'notfound' for a null booking.
	 */
	public function testValidateBookingReturnsNotFoundForNull(): void
	{
		$result = BookingUtils::validateBooking(null);

		$this->assertEquals('notfound', $result['code']);
		$this->assertNotNull($result['error']);
		$this->assertEquals('Booking Not Found', $result['error']['error']);
	}

	/**
	 * Test that validateBooking detects a booking whose tour date
	 * is more than a day in the future as 'early'.
	 */
	public function testValidateBookingDetectsEarlyStatus(): void
	{
		$booking = (object) [
			'status' => 'Booked',
			'deleted_by' => 0,
			'tour_d_t' => Carbon::now()->addDays(3)->toDateTimeString(),
		];

		$result = BookingUtils::validateBooking($booking);

		$this->assertEquals('early', $result['code']);
		$this->assertNotNull($result['error']);
		$this->assertStringContainsString('not scheduled today', $result['error']['error']);
	}

	/**
	 * Regression test for the bulkCheckIn -> TourBookingRepository::updateCheckInStatus
	 * path (see the "Deadlock found when trying to get lock" incident against
	 * tour_bookings_transact). Confirms that ordering the row lookup by id and
	 * adding deadlock-retry handling didn't change normal, non-contended check-in
	 * behavior: each tourist index in the group gets its own check-in status,
	 * recorded independently across sequential bulk check-in calls.
	 */
	public function testBulkCheckInSetsPerTouristStatus(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$tourOperatorId = DB::table('tour_packages')->where('id', $builder->packageId)->value('tour_operator_id');

		$checkInRepository = app(CheckInRepository::class);

		$checkins = [
			['booking_id' => $builder->bookingId, 'tpr_id' => $builder->rateId, 'index' => 0, 'check_in_status' => '1'],
			['booking_id' => $builder->bookingId, 'tpr_id' => $builder->rateId, 'index' => 1, 'check_in_status' => '1'],
		];

		$result = $checkInRepository->bulkCheckIn($checkins, $tourOperatorId);

		$this->assertCount(2, $result);
		$this->assertEquals('1st Check In', $result[0]['check_in_status']);
		$this->assertEquals('1st Check In', $result[1]['check_in_status']);

		$checkInStatus = json_decode(
			DB::table('tour_bookings_transact')->where('id', $builder->transactId)->value('check_in_status'),
			true
		);

		$this->assertCount(2, $checkInStatus);
		$this->assertEquals('1', $checkInStatus[0]['status']);
		$this->assertEquals('1', $checkInStatus[1]['status']);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$historyIds as $id) {
			DB::table('tour_booking_history')->where('id', $id)->delete();
		}
		static::$historyIds = [];

		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
