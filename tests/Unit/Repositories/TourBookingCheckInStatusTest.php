<?php

namespace Tests\Unit\Repositories;

use App\Models\Admin;
use App\Repositories\TourBooking\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class TourBookingCheckInStatusTest extends TestCase
{
	protected static array $builders = [];

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Regression test for the legacy (non-V3)
	 * App\Repositories\TourBooking\TourBookingRepository::updateCheckInStatus,
	 * which backs Common/TourBookingController and API/V1/TourBookingController.
	 * Mirrors the deadlock fix applied to the V3 repository (see
	 * Tests\Unit\SelfCheckIn\SelfCheckInTest::testBulkCheckInSetsPerTouristStatus
	 * for that counterpart): rows are now locked in a consistent order and
	 * deadlocks are retried. This confirms normal, non-contended check-in
	 * behavior is unchanged -- each tourist index gets its own status recorded
	 * independently across sequential calls.
	 */
	public function testUpdateCheckInStatusSetsPerTouristStatus(): void
	{
		$this->authAsAdmin();

		$builder = (new CreateABooking())
			->withDate(Carbon::today()->toDateString())
			->withTourists(2)
			->create();
		static::$builders[] = $builder;

		$tourOperatorId = DB::table('tour_packages')->where('id', $builder->packageId)->value('tour_operator_id');

		$repository = app(TourBookingRepository::class);

		foreach ([0, 1] as $index) {
			$request = new Request;
			$request->merge([
				'booking_id' => $builder->bookingId,
				'tpr_id' => $builder->rateId,
				'index' => $index,
				'check_in_status' => '1',
			]);

			$result = $repository->updateCheckInStatus($request, $tourOperatorId);
			$this->assertEquals('1st Check In', $result['check_in_status']);
		}

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
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
