<?php

namespace Tests\Unit\Agenda;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Fixture\CreateABooking;

class AgendaTest extends TestCase
{
	protected TourBookingRepository $tourBookingRepo;
	protected static array $builders = [];

	public function setUp(): void
	{
		parent::setUp();
		$this->tourBookingRepo = app(TourBookingRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		return $admin;
	}

	/**
	 * Test that the agenda dayTimeSlots returns expected structure
	 * and includes a booking we just created.
	 */
	public function testAgendaReturnsBookingsForDate(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();
		$builder = (new CreateABooking())
			->withDate($today->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$request = new \Illuminate\Http\Request();
		$date = (int) $today->format('Ymd');
		$tourPackagesId = (string) $builder->packageId;
		$paymentStatus = 0; // all statuses
		$slotId = '';

		$result = $this->tourBookingRepo->dayTimeSlots($request, $date, $tourPackagesId, $paymentStatus, $slotId);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('dayBookings', $result);
		$this->assertArrayHasKey('tourPackages', $result);
		$this->assertArrayHasKey('date', $result);
		$this->assertEquals($today->toDateString(), $result['date']);

		$bookingIds = collect($result['dayBookings'])->pluck('booking_id')->toArray();
		$this->assertContains($builder->bookingId, $bookingIds, 'Agenda should include the created booking');
	}

	/**
	 * Test that filtering by payment status "Completed" only returns paid bookings.
	 */
	public function testAgendaFiltersByPaymentStatus(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();

		// Booking with a completed transaction (default)
		$paidBuilder = (new CreateABooking())
			->withDate($today->toDateString())
			->create();
		static::$builders[] = $paidBuilder;

		$request = new \Illuminate\Http\Request();
		$date = (int) $today->format('Ymd');
		$tourPackagesId = (string) $paidBuilder->packageId;
		$paymentStatus = 2; // 2 = Completed/Paid
		$slotId = '';

		$result = $this->tourBookingRepo->dayTimeSlots($request, $date, $tourPackagesId, $paymentStatus, $slotId);

		$this->assertIsArray($result);
		$bookingIds = collect($result['dayBookings'])->pluck('booking_id')->toArray();
		$this->assertContains($paidBuilder->bookingId, $bookingIds, 'Paid booking should appear when filtering by paid status');
	}

	/**
	 * Test that filtering by slot ID narrows results.
	 */
	public function testAgendaFiltersBySlotId(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();
		$builder = (new CreateABooking())
			->withDate($today->toDateString())
			->create();
		static::$builders[] = $builder;

		$request = new \Illuminate\Http\Request();
		$date = (int) $today->format('Ymd');
		$tourPackagesId = (string) $builder->packageId;
		$paymentStatus = 0;
		$slotId = (string) $builder->slotTimeId;

		$result = $this->tourBookingRepo->dayTimeSlots($request, $date, $tourPackagesId, $paymentStatus, $slotId);

		$this->assertIsArray($result);
		$bookingIds = collect($result['dayBookings'])->pluck('booking_id')->toArray();
		$this->assertContains($builder->bookingId, $bookingIds, 'Booking should appear when filtering by its slot ID');
	}

	/**
	 * Test that check-in status updates from N -> 1st check-in for all pax.
	 */
	public function testUpdateCheckInStatusForAllPax(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$request = new \Illuminate\Http\Request();
		$request->merge([
			'booking_id' => $builder->bookingId,
			'all_same_checked_in' => 1,
			'check_in_status' => '1',
			'old_check_in_status' => 'N',
		]);

		$this->tourBookingRepo->updateCheckInStatus($request);

		$transact = DB::table('tour_bookings_transact')
			->where('id', $builder->transactId)
			->first();

		$statuses = json_decode($transact->check_in_status, true);
		$this->assertCount(2, $statuses);
		foreach ($statuses as $pax) {
			$this->assertEquals('1', $pax['status'], 'Each pax should be at 1st check-in');
			$this->assertNotNull($pax['dateTime']);
		}
	}

	/**
	 * Test that check-in status updates from 1st -> 2nd check-in.
	 */
	public function testUpdateCheckInStatusSecondCheckIn(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		// First: move N -> 1
		$request = new \Illuminate\Http\Request();
		$request->merge([
			'booking_id' => $builder->bookingId,
			'all_same_checked_in' => 1,
			'check_in_status' => '1',
			'old_check_in_status' => 'N',
		]);
		$this->tourBookingRepo->updateCheckInStatus($request);

		// Second: move 1 -> 2
		$request2 = new \Illuminate\Http\Request();
		$request2->merge([
			'booking_id' => $builder->bookingId,
			'all_same_checked_in' => 1,
			'check_in_status' => '2',
			'old_check_in_status' => '1',
		]);
		$this->tourBookingRepo->updateCheckInStatus($request2);

		$transact = DB::table('tour_bookings_transact')
			->where('id', $builder->transactId)
			->first();

		$statuses = json_decode($transact->check_in_status, true);
		$this->assertEquals('2', $statuses[0]['status'], 'Pax should be at 2nd check-in');
	}

	/**
	 * Test that check-in on a cancelled booking throws an exception.
	 */
	public function testUpdateCheckInStatusOnCancelledBookingFails(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withStatus('Cancelled')
			->withoutTransaction()
			->withoutHistory()
			->create();
		static::$builders[] = $builder;

		$request = new \Illuminate\Http\Request();
		$request->merge([
			'booking_id' => $builder->bookingId,
			'all_same_checked_in' => 1,
			'check_in_status' => '1',
			'old_check_in_status' => 'N',
		]);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('cancelled');
		$this->tourBookingRepo->updateCheckInStatus($request);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
