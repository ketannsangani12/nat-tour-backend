<?php

namespace Tests\Unit\Calendar;

use App\Models\Admin;
use App\Models\Customer;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Repositories\TourSlot\TourSlotRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class CalendarTest extends TestCase
{
	protected TourBookingRepository $tourBookingRepo;
	protected TourSlotRepository $tourSlotRepo;
	protected static array $builders = [];

	public function setUp(): void
	{
		parent::setUp();
		$this->tourBookingRepo = app(TourBookingRepository::class);
		$this->tourSlotRepo = app(TourSlotRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		return $admin;
	}

	protected function authAsAffiliate(int $tourOperatorId): Customer
	{
		$now = Carbon::now();
		$customerId = DB::table('customers')->insertGetId([
			'tour_operator_id' => $tourOperatorId,
			'name' => 'Test Affiliate',
			'email' => 'affiliate-' . uniqid() . '@test.com',
			'phone_number' => '5550002222',
			'is_affiliate' => 'Yes',
			'status' => 'Active',
			'remember_token' => '',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
		]);

		$affiliate = Customer::find($customerId);
		$this->actingAs($affiliate, 'affiliate');
		Session::put('guard', 'affiliate');

		return $affiliate;
	}

	/**
	 * Test that the calendar returns expected structure for the current month.
	 */
	public function testCalendarReturnsStructure(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();
		$builder = (new CreateABooking())
			->withDate($today->toDateString())
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$request = new Request();
		$request->merge([
			'year' => $today->year,
			'month' => $today->month,
			'tourOperatorId' => $tourOperatorId,
		]);

		$result = $this->tourBookingRepo->calendar($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('tourBookings', $result);
		$this->assertArrayHasKey('currentDate', $result);
		$this->assertArrayHasKey('tourOperatorId', $result);
	}

	/**
	 * Test that getTourSlotTimesByDate returns slot data for a date
	 * with a known booking.
	 */
	public function testGetTourSlotTimesByDate(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();
		$builder = (new CreateABooking())
			->withDate($today->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'date' => $today->format('Ymd'),
			'tourPackageId' => $builder->packageId,
			'getRateGroups' => 0,
			'allBookableStatus' => 0,
		]);

		$result = $this->tourSlotRepo->getTourSlotTimesByDate($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('slotTimes', $result);
	}

	/**
	 * Test that timeSlotBookingsInfo returns bookings and seat info
	 * for a slot with a known booking.
	 */
	public function testTimeSlotBookingsInfo(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(3)
			->create();
		static::$builders[] = $builder;

		$result = $this->tourSlotRepo->timeSlotBookingsInfo($builder->slotTimeId);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('bookings', $result);
		$this->assertArrayHasKey('totalTourists', $result);
		$this->assertArrayHasKey('availableSeats', $result);
		$this->assertGreaterThanOrEqual(3, $result['totalTourists'], 'Slot should reflect at least our 3 tourists');
	}

	/**
	 * Test that timeSlotTouristsInfo returns rate-group tourist counts.
	 */
	public function testTimeSlotTouristsInfo(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$result = $this->tourSlotRepo->timeSlotTouristsInfo($builder->slotTimeId);

		$this->assertNotNull($result);
		$this->assertGreaterThanOrEqual(1, count($result), 'Should have at least one rate group');
	}

	/**
	 * Test that affiliateBookings returns a JSON response for a given slot.
	 */
	public function testAffiliateBookingsReturnsResponse(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->create();
		static::$builders[] = $builder;

		$response = $this->tourBookingRepo->affiliateBookings($builder->slotTimeId);

		$this->assertNotNull($response);
	}

	/**
	 * Test that booking() returns full booking detail for a known booking.
	 */
	public function testBookingInfoReturnsDetail(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withTourists(2)
			->withRate(75.00)
			->withCustomer('Calendar Test', 'calendar-' . uniqid() . '@test.com', '5550009999')
			->create();
		static::$builders[] = $builder;

		$result = $this->tourBookingRepo->booking($builder->bookingId, false);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('page_title', $result);
		$this->assertArrayHasKey('bookingM', $result);
		$this->assertEquals('Calendar Test', $result['bookingM']->customer);
	}

	/**
	 * Test that calendar with a future month still returns a valid structure.
	 */
	public function testCalendarForFutureMonth(): void
	{
		$this->authAsAdmin();

		$nextMonth = Carbon::today()->addMonth();
		$tourOperatorId = DB::table('tour_operators')->value('id');

		$request = new Request();
		$request->merge([
			'year' => $nextMonth->year,
			'month' => $nextMonth->month,
			'tourOperatorId' => $tourOperatorId,
		]);

		$result = $this->tourBookingRepo->calendar($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('tourBookings', $result);
	}

	/**
	 * Regression test: filtering the calendar to a single child package that shares
	 * seat capacity with a parent package (PACKAGE_RELATION) must not force available
	 * seats to 0. The fetch behind the filtered view needs to include the parent's
	 * rows too so the shared-capacity math has data to work with.
	 */
	public function testCalendarChildPackageAvailabilityWithPackageFilter(): void
	{
		$this->authAsAdmin();

		$today = Carbon::today();
		$timeFrom = '09:00:00';
		$timeTo = '11:00:00';
		$seats = 6;
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$parentBuilder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTimeSlot($timeFrom, $timeTo)
			->withSeats($seats)
			->withTourists(2)
			->create();
		static::$builders[] = $parentBuilder;

		$childBuilder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTimeSlot($timeFrom, $timeTo)
			->withSeats($seats)
			->withTourists(1)
			->create();
		static::$builders[] = $childBuilder;

		$now = Carbon::now();
		DB::table('tour_package_families')->insert([
			[
				'tour_operator_id' => $tourOperatorId,
				'parent_package_id' => $parentBuilder->packageId,
				'tour_package_id' => $parentBuilder->packageId,
				'time_from' => $timeFrom,
				'is_parent' => 1,
				'group_index' => 1,
				'relation_year' => $today->year,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[
				'tour_operator_id' => $tourOperatorId,
				'parent_package_id' => $parentBuilder->packageId,
				'tour_package_id' => $childBuilder->packageId,
				'time_from' => $timeFrom,
				'is_parent' => 0,
				'group_index' => 1,
				'relation_year' => $today->year,
				'created_at' => $now,
				'updated_at' => $now,
			],
		]);

		$settingExisted = DB::table('settings')->where('key', 'PACKAGE_RELATION')->exists();
		$previousSettingValue = $settingExisted
			? DB::table('settings')->where('key', 'PACKAGE_RELATION')->value('value')
			: null;

		if ($settingExisted) {
			DB::table('settings')->where('key', 'PACKAGE_RELATION')->update(['value' => 1]);
		} else {
			DB::table('settings')->insert([
				'key' => 'PACKAGE_RELATION',
				'value' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}

		Session::put('package_id', [$childBuilder->packageId]);

		try {
			$request = new Request();
			$request->merge([
				'year' => $today->year,
				'month' => $today->month,
				'tourOperatorId' => $tourOperatorId,
			]);

			$result = $this->tourBookingRepo->calendar($request);
		} finally {
			Session::forget('package_id');
			DB::table('tour_package_families')->where('parent_package_id', $parentBuilder->packageId)->delete();

			if ($settingExisted) {
				DB::table('settings')->where('key', 'PACKAGE_RELATION')->update(['value' => $previousSettingValue]);
			} else {
				DB::table('settings')->where('key', 'PACKAGE_RELATION')->delete();
			}
		}

		$this->assertArrayHasKey('tourSlots', $result);

		$childSlot = collect($result['tourSlots'])->firstWhere('pkg_id', $childBuilder->packageId);
		$this->assertNotNull($childSlot, 'Filtered calendar should still return the requested child package');

		// Total group demand (2 parent + 1 child = 3) is well under the shared 6-seat
		// capacity, so the child's available seats must reflect real remaining capacity
		// instead of collapsing to 0 just because the view is filtered to one package.
		$this->assertGreaterThan(
			0,
			$childSlot['available_seats'],
			'Child package availability should not collapse to 0 when the calendar is filtered to just that package'
		);

		// The parent was only fetched internally for capacity math -- it must not leak
		// into the displayed rows for a view the admin filtered to the child package.
		$parentSlot = collect($result['tourSlots'])->firstWhere('pkg_id', $parentBuilder->packageId);
		$this->assertNull($parentSlot, 'Parent package should not appear in a calendar filtered to the child package');
	}

	/**
	 * Regression test: an affiliate assigned to a child package but NOT to its parent
	 * must still have the parent's bookings counted against the shared capacity. The
	 * affiliate-scoped join in fetchDateSlots() excludes any package the affiliate
	 * isn't assigned to, so without a direct fallback lookup the parent's bookings are
	 * invisible and available seats are overstated -- a real overbooking risk.
	 */
	public function testCalendarAffiliateChildPackageAvailabilityWhenParentUnassigned(): void
	{
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$today = Carbon::today();
		$timeFrom = '09:00:00';
		$timeTo = '11:00:00';
		$seats = 6;

		// Parent package booked by a regular (non-affiliate) customer.
		$parentBuilder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTimeSlot($timeFrom, $timeTo)
			->withSeats($seats)
			->withTourists(2)
			->create();
		static::$builders[] = $parentBuilder;

		// Child package -- no bookings of its own, just a slot to filter/view.
		$childBuilder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTimeSlot($timeFrom, $timeTo)
			->withSeats($seats)
			->withTourists(0)
			->create();
		static::$builders[] = $childBuilder;

		$now = Carbon::now();
		DB::table('tour_package_families')->insert([
			[
				'tour_operator_id' => $tourOperatorId,
				'parent_package_id' => $parentBuilder->packageId,
				'tour_package_id' => $parentBuilder->packageId,
				'time_from' => $timeFrom,
				'is_parent' => 1,
				'group_index' => 1,
				'relation_year' => $today->year,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[
				'tour_operator_id' => $tourOperatorId,
				'parent_package_id' => $parentBuilder->packageId,
				'tour_package_id' => $childBuilder->packageId,
				'time_from' => $timeFrom,
				'is_parent' => 0,
				'group_index' => 1,
				'relation_year' => $today->year,
				'created_at' => $now,
				'updated_at' => $now,
			],
		]);

		$affiliate = $this->authAsAffiliate($tourOperatorId);

		// Affiliate is assigned to the child package only -- the parent is deliberately
		// left unassigned, which is what triggers the affiliate-scoped join gap.
		$assignmentId = DB::table('package_affiliate_assignments')->insertGetId([
			'package_id' => $childBuilder->packageId,
			'affiliate_id' => $affiliate->id,
			'show_all_package_bookings' => 0,
			'created_at' => $now,
			'created_by_user_type' => 'Admin',
		]);

		$tstaId = DB::table('tour_slot_times_affiliate')->insertGetId([
			'tour_package_id' => $childBuilder->packageId,
			'time_from' => $timeFrom,
			'time_to' => $timeTo,
			'status' => 'Active',
			'created_at' => $now,
			'created_by_user_type' => 'Tour Operator',
		]);

		$astId = DB::table('affiliate_slot_times')->insertGetId([
			'tour_slot_time_id' => $tstaId,
			'affiliate_id' => $affiliate->id,
			'seat_limit' => $seats,
			'created_at' => $now,
			'created_by_user_type' => 'Tour Operator',
		]);

		$settingExisted = DB::table('settings')->where('key', 'PACKAGE_RELATION')->exists();
		$previousSettingValue = $settingExisted
			? DB::table('settings')->where('key', 'PACKAGE_RELATION')->value('value')
			: null;

		if ($settingExisted) {
			DB::table('settings')->where('key', 'PACKAGE_RELATION')->update(['value' => 1]);
		} else {
			DB::table('settings')->insert([
				'key' => 'PACKAGE_RELATION',
				'value' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}

		Session::put('package_id', [$childBuilder->packageId]);

		try {
			$request = new Request();
			$request->merge([
				'year' => $today->year,
				'month' => $today->month,
				'tourOperatorId' => $tourOperatorId,
			]);

			$result = $this->tourBookingRepo->calendar($request);
		} finally {
			Session::forget('package_id');
			Session::forget('guard');
			DB::table('affiliate_slot_times')->where('id', $astId)->delete();
			DB::table('tour_slot_times_affiliate')->where('id', $tstaId)->delete();
			DB::table('package_affiliate_assignments')->where('id', $assignmentId)->delete();
			DB::table('tour_package_families')->where('parent_package_id', $parentBuilder->packageId)->delete();
			DB::table('customers')->where('id', $affiliate->id)->delete();

			if ($settingExisted) {
				DB::table('settings')->where('key', 'PACKAGE_RELATION')->update(['value' => $previousSettingValue]);
			} else {
				DB::table('settings')->where('key', 'PACKAGE_RELATION')->delete();
			}
		}

		$this->assertArrayHasKey('tourSlots', $result);

		$childSlot = collect($result['tourSlots'])->firstWhere('pkg_id', $childBuilder->packageId);
		$this->assertNotNull($childSlot, 'Filtered affiliate calendar should still return the assigned child package');

		// The shared pool has 6 seats and the parent already used 2 of them, so the
		// affiliate's remaining availability must be 4 -- not 6, which is what you'd
		// get if the parent's bookings were invisible to the affiliate-scoped fetch.
		$this->assertEquals(
			4,
			$childSlot['available_seats'],
			'Affiliate availability must subtract the unassigned parent package\'s bookings, not just its own'
		);
	}

	/**
	 * Regression test: an affiliate assigned via show_all_package_bookings = 1 (sees all
	 * bookings, no per-slot seat limit needed) never gets tour_slot_times_affiliate /
	 * affiliate_slot_times rows configured -- there's nothing to limit. The affiliate
	 * calendar join must not require those rows to exist, or the whole calendar comes
	 * back empty for every such affiliate on initial page load.
	 */
	public function testCalendarAffiliateWithShowAllBookingsAndNoSeatLimitRows(): void
	{
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$today = Carbon::today();

		$builder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTourists(0)
			->create();
		static::$builders[] = $builder;

		$affiliate = $this->authAsAffiliate($tourOperatorId);

		$assignmentId = DB::table('package_affiliate_assignments')->insertGetId([
			'package_id' => $builder->packageId,
			'affiliate_id' => $affiliate->id,
			'show_all_package_bookings' => 1,
			'created_at' => Carbon::now(),
			'created_by_user_type' => 'Admin',
		]);

		// Deliberately no tour_slot_times_affiliate / affiliate_slot_times rows --
		// that's the real-world state for a show-all-bookings affiliate.

		try {
			$request = new Request();
			$request->merge([
				'year' => $today->year,
				'month' => $today->month,
				'tourOperatorId' => $tourOperatorId,
			]);

			$result = $this->tourBookingRepo->calendar($request);
		} finally {
			Session::forget('guard');
			DB::table('package_affiliate_assignments')->where('id', $assignmentId)->delete();
			DB::table('customers')->where('id', $affiliate->id)->delete();
		}

		$this->assertArrayHasKey('tourSlots', $result);

		$slot = collect($result['tourSlots'])->firstWhere('pkg_id', $builder->packageId);
		$this->assertNotNull(
			$slot,
			'Affiliate calendar must still return packages assigned via show_all_package_bookings even without any per-slot seat-limit rows'
		);
	}

	/**
	 * Test that timeSlotBookingsInfo reports cancelled bookings separately.
	 */
	public function testTimeSlotBookingsInfoSeparatesCancelledBookings(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(1)
			->withStatus('Cancelled')
			->withoutTransaction()
			->withoutHistory()
			->create();
		static::$builders[] = $builder;

		$result = $this->tourSlotRepo->timeSlotBookingsInfo($builder->slotTimeId);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('bookingsCancelled', $result);
	}

	/**
	 * Test that updateSlotHideStatus toggles hide_slot from 0 to 1.
	 */
	public function testUpdateSlotHideStatusHidesVisibleSlot(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		// Ensure the slot starts as visible (hide_slot = 0)
		DB::table('tour_slot_times')
			->where('id', $builder->slotTimeId)
			->update(['hide_slot' => 0]);

		$request = new Request();
		$request->merge(['slot_id' => $builder->slotTimeId]);

		$result = $this->tourBookingRepo->updateSlotHideStatus($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('hide_slot', $result);
		$this->assertEquals(1, $result['hide_slot'], 'Slot should be hidden after toggling from visible');

		$dbValue = DB::table('tour_slot_times')->where('id', $builder->slotTimeId)->value('hide_slot');
		$this->assertEquals(1, $dbValue, 'Database should reflect hidden state');
	}

	/**
	 * Test that updateSlotHideStatus toggles hide_slot from 1 back to 0.
	 */
	public function testUpdateSlotHideStatusMakesHiddenSlotVisible(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		// Set slot as hidden first
		DB::table('tour_slot_times')
			->where('id', $builder->slotTimeId)
			->update(['hide_slot' => 1]);

		$request = new Request();
		$request->merge(['slot_id' => $builder->slotTimeId]);

		$result = $this->tourBookingRepo->updateSlotHideStatus($request);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['hide_slot'], 'Slot should be visible after toggling from hidden');

		$dbValue = DB::table('tour_slot_times')->where('id', $builder->slotTimeId)->value('hide_slot');
		$this->assertEquals(0, $dbValue, 'Database should reflect visible state');
	}

	/**
	 * Test that updateSlotHideStatus logs a record in records_history.
	 */
	public function testUpdateSlotHideStatusLogsHistory(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		DB::table('tour_slot_times')
			->where('id', $builder->slotTimeId)
			->update(['hide_slot' => 0]);

		$countBefore = DB::table('records_history')
			->where('record_id_1', $builder->slotTimeId)
			->where('table_name', 'tour_slot_times')
			->count();

		$request = new Request();
		$request->merge(['slot_id' => $builder->slotTimeId]);

		$this->tourBookingRepo->updateSlotHideStatus($request);

		$countAfter = DB::table('records_history')
			->where('record_id_1', $builder->slotTimeId)
			->where('table_name', 'tour_slot_times')
			->count();

		$this->assertEquals($countBefore + 1, $countAfter, 'A history record should be created on visibility update');
	}

	/**
	 * Test that bulkUpdateSlotHideStatus sets hide_slot = 1 for all given slot IDs.
	 */
	public function testBulkUpdateSlotHideStatusHidesMultipleSlots(): void
	{
		$this->authAsAdmin();

		$builder1 = (new CreateABooking())->withDate(Carbon::today()->toDateString())->withTourists(1)->create();
		$builder2 = (new CreateABooking())->withDate(Carbon::today()->toDateString())->withTourists(1)->create();
		static::$builders[] = $builder1;
		static::$builders[] = $builder2;

		$slotIds = [$builder1->slotTimeId, $builder2->slotTimeId];

		// Start both visible
		DB::table('tour_slot_times')->whereIn('id', $slotIds)->update(['hide_slot' => 0]);

		$request = new Request();
		$request->merge(['slotIds' => $slotIds, 'hide_slot' => 1]);

		$result = $this->tourBookingRepo->bulkUpdateSlotHideStatus($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('hide_slot', $result);
		$this->assertEquals(1, $result['hide_slot']);

		$hiddenCount = DB::table('tour_slot_times')
			->whereIn('id', $slotIds)
			->where('hide_slot', 1)
			->count();

		$this->assertEquals(2, $hiddenCount, 'Both slots should be hidden in the database');
	}

	/**
	 * Test that bulkUpdateSlotHideStatus sets hide_slot = 0 for all given slot IDs.
	 */
	public function testBulkUpdateSlotHideStatusMakesMultipleSlotsVisible(): void
	{
		$this->authAsAdmin();

		$builder1 = (new CreateABooking())->withDate(Carbon::today()->toDateString())->withTourists(1)->create();
		$builder2 = (new CreateABooking())->withDate(Carbon::today()->toDateString())->withTourists(1)->create();
		static::$builders[] = $builder1;
		static::$builders[] = $builder2;

		$slotIds = [$builder1->slotTimeId, $builder2->slotTimeId];

		// Start both hidden
		DB::table('tour_slot_times')->whereIn('id', $slotIds)->update(['hide_slot' => 1]);

		$request = new Request();
		$request->merge(['slotIds' => $slotIds, 'hide_slot' => 0]);

		$result = $this->tourBookingRepo->bulkUpdateSlotHideStatus($request);

		$this->assertEquals(0, $result['hide_slot']);

		$visibleCount = DB::table('tour_slot_times')
			->whereIn('id', $slotIds)
			->where('hide_slot', 0)
			->count();

		$this->assertEquals(2, $visibleCount, 'Both slots should be visible in the database');
	}

	/**
	 * Regression test: CalendarQueryService::fetchDateSlots() is now wrapped in a short-TTL
	 * cache. A calendar() call made right after a booking is created directly against the
	 * same slot (bypassing the cache) must still serve the pre-booking, cached tourist
	 * count -- proving the cache is actually wired in, not a no-op. Flushing the cache must
	 * then make the next call see the real, up-to-date count.
	 */
	public function testCalendarDateSlotsAreCachedWithinTTL(): void
	{
		$this->authAsAdmin();

		// Other tests in this class also call calendar() as the same admin for the current
		// month/year -- without a flush here, this test's first call could be served one of
		// their already-cached responses (from before this test's own package existed).
		Cache::flush();

		$today = Carbon::today();
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$builder = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withSeats(20)
			->withTourists(1)
			->create();
		static::$builders[] = $builder;

		$request = new Request();
		$request->merge([
			'year' => $today->year,
			'month' => $today->month,
			'tourOperatorId' => $tourOperatorId,
		]);

		$firstResult = $this->tourBookingRepo->calendar($request);
		$firstSlot = collect($firstResult['tourSlots'])->firstWhere('pkg_id', $builder->packageId);
		$this->assertNotNull($firstSlot, 'Initial calendar call should return the seeded slot');
		$initialTourists = $firstSlot['tourists'];

		// Book directly onto the same slot -- this write bypasses the cache entirely.
		$secondBuilder = (new CreateABooking())
			->usePackage($builder->packageId)
			->useSlotTime($builder->slotTimeId)
			->withTourists(2)
			->create();
		static::$builders[] = $secondBuilder;

		try {
			$cachedResult = $this->tourBookingRepo->calendar($request);
			$cachedSlot = collect($cachedResult['tourSlots'])->firstWhere('pkg_id', $builder->packageId);
			$this->assertEquals(
				$initialTourists,
				$cachedSlot['tourists'],
				'A calendar call within the cache TTL must not reflect a booking made directly in between -- otherwise fetchDateSlots() is not actually being cached'
			);

			Cache::flush();

			$freshResult = $this->tourBookingRepo->calendar($request);
			$freshSlot = collect($freshResult['tourSlots'])->firstWhere('pkg_id', $builder->packageId);
			$this->assertEquals(
				$initialTourists + 2,
				$freshSlot['tourists'],
				'After flushing the cache, the next calendar call must see the booking made in between'
			);
		} finally {
			Cache::flush();
		}
	}

	/**
	 * Regression test: two different affiliates assigned to two different packages must
	 * never share a fetchDateSlots() cache entry. generateAffiliateJoinQuery() INNER JOINs
	 * package_affiliate_assignments on Auth::id(), which controls WHICH PACKAGES are even
	 * present in the cached row set -- unlike the per-affiliate tourist/seat counts (which
	 * TourBookingRepository::calendar() recomputes in a separate, always-fresh lookup after
	 * fetchDateSlots() returns, see calendar()'s $affiliateTouristsLookup/
	 * $affiliateSeatsLookup block), package visibility itself is baked into the cached SQL
	 * result with no fresh recomputation downstream. If the cache key ignored Auth::id(),
	 * the second affiliate to view the calendar within the TTL would be served the first
	 * affiliate's package list instead of her own.
	 */
	public function testCalendarDateSlotsCacheIsIsolatedPerAffiliate(): void
	{
		// Fresh affiliates below always get a brand-new Auth::id(), so this isn't strictly
		// needed for correctness -- but it keeps this test independent of leftover cache
		// state from whichever test ran before it, same as the TTL test above.
		Cache::flush();

		$tourOperatorId = DB::table('tour_operators')->value('id');
		$this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

		$today = Carbon::today();

		// Package A: only affiliate A is assigned to it.
		$builderA = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTourists(0)
			->create();
		static::$builders[] = $builderA;

		// Package B: only affiliate B is assigned to it.
		$builderB = (new CreateABooking())
			->withNewPackage()
			->withDate($today->toDateString())
			->withTourists(0)
			->create();
		static::$builders[] = $builderB;

		$now = Carbon::now();
		$request = new Request();
		$request->merge([
			'year' => $today->year,
			'month' => $today->month,
			'tourOperatorId' => $tourOperatorId,
		]);

		$affiliateA = $this->authAsAffiliate($tourOperatorId);
		$assignmentAId = DB::table('package_affiliate_assignments')->insertGetId([
			'package_id' => $builderA->packageId,
			'affiliate_id' => $affiliateA->id,
			'show_all_package_bookings' => 1,
			'created_at' => $now,
			'created_by_user_type' => 'Admin',
		]);

		$affiliateAResult = $this->tourBookingRepo->calendar($request);
		$affiliateASlots = collect($affiliateAResult['tourSlots'])->pluck('pkg_id');
		$this->assertTrue($affiliateASlots->contains($builderA->packageId), 'Affiliate A should see her own assigned package');
		$this->assertFalse($affiliateASlots->contains($builderB->packageId), 'Affiliate A should not see package B, which she is not assigned to');

		$affiliateB = $this->authAsAffiliate($tourOperatorId);
		$assignmentBId = DB::table('package_affiliate_assignments')->insertGetId([
			'package_id' => $builderB->packageId,
			'affiliate_id' => $affiliateB->id,
			'show_all_package_bookings' => 1,
			'created_at' => $now,
			'created_by_user_type' => 'Admin',
		]);

		try {
			// Made within the same TTL window as A's call above, with no cache flush in
			// between -- this is exactly the scenario a shared/under-scoped cache key would
			// get wrong.
			$affiliateBResult = $this->tourBookingRepo->calendar($request);
			$affiliateBSlots = collect($affiliateBResult['tourSlots'])->pluck('pkg_id');
			$this->assertTrue(
				$affiliateBSlots->contains($builderB->packageId),
				'Affiliate B must see her own assigned package B, not just a reused cached response scoped to affiliate A'
			);
			$this->assertFalse(
				$affiliateBSlots->contains($builderA->packageId),
				'Affiliate B must not see package A -- a shared cache key would leak affiliate A\'s package visibility here'
			);
		} finally {
			Session::forget('guard');
			DB::table('package_affiliate_assignments')->whereIn('id', [$assignmentAId, $assignmentBId])->delete();
			DB::table('customers')->whereIn('id', [$affiliateA->id, $affiliateB->id])->delete();
			Cache::flush();
		}
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
