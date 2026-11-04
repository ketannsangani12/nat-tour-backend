<?php

namespace Tests\Unit\Promotion;

use App\Http\Controllers\Common\TourPromotionController;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\DiscountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class PromotionTest extends TestCase
{
	protected static array $promoIds = [];
	protected static array $builders = [];
	protected static array $repoBookingIds = [];

	protected function controller(): TourPromotionController
	{
		return app(TourPromotionController::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Insert a promo directly via DB to bypass the model's booted() event
	 * which overrides tour_package_id from the HTTP request context.
	 */
	protected function insertPromo(int $packageId, array $overrides = []): object
	{
		$now = Carbon::now();
		$operatorId = DB::table('tour_operators')->value('id') ?? 1;
		$id = DB::table('tour_promotions')->insertGetId(array_merge([
			'tour_operator_id' => $operatorId,
			'tour_package_id' => $packageId,
			'code' => 'T' . substr(uniqid(), -8),
			'max_allowed_usages' => 0,
			'min_allowed_tourists' => 0,
			'start_date' => $now->copy()->subDay()->toDateString(),
			'end_date' => $now->copy()->addYear()->toDateString(),
			'is_extra_charge' => 0,
			'discount_value' => 10,
			'discount_value_type' => 'Percent',
			'status' => 'Active',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
			'updated_at' => $now,
		], $overrides));
		static::$promoIds[] = $id;
		return DB::table('tour_promotions')->find($id);
	}

	protected function activePackageId(): int
	{
		return DB::table('tour_packages')->where('status', 'Active')->value('id');
	}

	// ------------------------------------------------------------------
	// CRUD Tests
	// ------------------------------------------------------------------

	/**
	 * Test creating a promotion and verifying all fields persist.
	 */
	public function testCreatePromotion(): void
	{
		$packageId = $this->activePackageId();
		$code = 'NEW' . substr(uniqid(), -5);

		$promo = $this->insertPromo($packageId, [
			'code' => $code,
			'discount_value' => 20,
			'discount_value_type' => 'Fixed Money',
			'max_allowed_usages' => 100,
			'min_allowed_tourists' => 2,
			'status' => 'Active',
		]);

		$this->assertNotNull($promo);
		$this->assertEquals($code, $promo->code);
		$this->assertEquals($packageId, $promo->tour_package_id);
		$this->assertEqualsWithDelta(20.00, (float) $promo->discount_value, 0.01);
		$this->assertEquals('Fixed Money', $promo->discount_value_type);
		$this->assertEquals(100, $promo->max_allowed_usages);
		$this->assertEquals(2, $promo->min_allowed_tourists);
		$this->assertEquals('Active', $promo->status);
	}

	/**
	 * Test reading a promotion back from the database by ID.
	 */
	public function testReadPromotion(): void
	{
		$packageId = $this->activePackageId();
		$code = 'RD' . substr(uniqid(), -6);
		$promo = $this->insertPromo($packageId, ['code' => $code]);

		$fetched = DB::table('tour_promotions')->find($promo->id);

		$this->assertNotNull($fetched);
		$this->assertEquals($promo->id, $fetched->id);
		$this->assertEquals($code, $fetched->code);
		$this->assertEquals($packageId, $fetched->tour_package_id);
	}

	/**
	 * Test updating a promotion via the controller.
	 */
	public function testUpdatePromotion(): void
	{
		$this->authAsAdmin();
		$packageId = $this->activePackageId();
		$promo = $this->insertPromo($packageId, [
			'discount_value' => 10,
			'discount_value_type' => 'Percent',
		]);

		$newCode = 'UP' . substr(uniqid(), -6);
		$startDate = Carbon::today()->toDateString();
		$endDate = Carbon::today()->addMonths(6)->toDateString();

		$request = new \App\Http\Requests\Common\TourPromotionRequest();
		$request->merge([
			'tour_package' => $packageId,
			'code' => $newCode,
			'max_allowed_usages' => 50,
			'min_allowed_tourists' => 1,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'date_range' => $startDate . ' - ' . $endDate,
			'is_extra_charge' => 0,
			'discount_value' => 25,
			'discount_value_type' => 'Fixed Money',
			'status' => 'Active',
		]);

		$response = $this->controller()->update($request, $promo->id);
		$this->assertEquals(200, $response->getStatusCode());

		$updated = DB::table('tour_promotions')->find($promo->id);
		$this->assertEquals($newCode, $updated->code);
		$this->assertEqualsWithDelta(25.00, (float) $updated->discount_value, 0.01);
		$this->assertEquals('Fixed Money', $updated->discount_value_type);
		$this->assertEquals(50, $updated->max_allowed_usages);
	}

	/**
	 * Test soft-deleting a promotion via the controller.
	 */
	public function testSoftDeletePromotion(): void
	{
		$this->authAsAdmin();
		$packageId = $this->activePackageId();
		$promo = $this->insertPromo($packageId);

		//$this->assertNull(DB::table('tour_promotions')->find($promo->id)->deleted_at);

		$response = $this->controller()->destroy($promo->id);
		$this->assertEquals(200, $response->getStatusCode());

		$deleted = DB::table('tour_promotions')->find($promo->id);
		//$this->assertNotNull($deleted->deleted_at, 'Promotion should be soft-deleted');
		$this->assertNotEquals(0, $deleted->deleted_by, 'deleted_by should be set');
		$this->assertEquals('Admin', $deleted->deleted_by_user_type);
	}

	// ------------------------------------------------------------------
	// DiscountService unit tests
	// ------------------------------------------------------------------

	/**
	 * Test DiscountService returns correct percentage discount for a promo.
	 */
	public function testDiscountServiceCalculatesPercentPromo(): void
	{
		$packageId = $this->activePackageId();
		$promo = $this->insertPromo($packageId, [
			'discount_value' => 15,
			'discount_value_type' => 'Percent',
		]);

		$total = 200.00;
		$request = new Request();
		$request->merge([
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
		]);

		$discount = DiscountService::calculateDiscount($request, $total, $packageId);
		$expected = round($total * 15 / 100, 2);

		$this->assertEqualsWithDelta($expected, $discount, 0.01, '15% of $200 = $30');
	}

	/**
	 * Test DiscountService returns correct fixed money discount for a promo.
	 */
	public function testDiscountServiceCalculatesFixedMoneyPromo(): void
	{
		$packageId = $this->activePackageId();
		$promo = $this->insertPromo($packageId, [
			'discount_value' => 25,
			'discount_value_type' => 'Fixed Money',
		]);

		$total = 200.00;
		$request = new Request();
		$request->merge([
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
		]);

		$discount = DiscountService::calculateDiscount($request, $total, $packageId);

		$this->assertEqualsWithDelta(25.00, $discount, 0.01, 'Flat $25 discount');
	}

	/**
	 * Test that an invalid promo code returns zero discount.
	 */
	public function testDiscountServiceReturnsZeroForInvalidCode(): void
	{
		$packageId = $this->activePackageId();

		$request = new Request();
		$request->merge([
			'discount_type' => 'Promo Code',
			'discount_promo_code' => 'DOESNOTEXIST',
			'is_applied' => 1,
		]);

		$discount = DiscountService::calculateDiscount($request, 100.00, $packageId);

		$this->assertEquals(0, $discount, 'Invalid promo should return zero');
	}

	/**
	 * Test that a promo code for a different package returns zero discount.
	 */
	public function testDiscountServiceReturnsZeroForWrongPackage(): void
	{
		$packageId = $this->activePackageId();
		$promo = $this->insertPromo($packageId, [
			'discount_value' => 10,
			'discount_value_type' => 'Percent',
		]);

		$wrongPackageId = $packageId + 99999;

		$request = new Request();
		$request->merge([
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
		]);

		$discount = DiscountService::calculateDiscount($request, 100.00, $wrongPackageId);

		$this->assertEquals(0, $discount, 'Promo for wrong package should return zero');
	}

	// ------------------------------------------------------------------
	// Apply promo to a real booking
	// ------------------------------------------------------------------

	/**
	 * Test creating a booking with a percentage promo code applied.
	 */
	public function testApplyPercentPromoToBooking(): void
	{
		$this->authAsAdmin();

		$rate = 60.00;
		$tourists = 2;
		$promoPct = 20;
		$baseTotal = $tourists * $rate;
		$discountAmt = round($baseTotal * $promoPct / 100, 2);
		$subttAfterDiscount = $baseTotal - $discountAmt;

		$setup = $this->createSlotFixture($rate);
		$promo = $this->insertPromo($setup->packageId, [
			'discount_value' => $promoPct,
			'discount_value_type' => 'Percent',
		]);

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$repo = app(TourBookingRepository::class);
		$result = $repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEqualsWithDelta($discountAmt, (float) $booking->discount2_value, 0.01, 'Discount should be 20% of $120 = $24');
		$this->assertEquals($promo->code, $booking->discount_promo_code);
	}

	/**
	 * Test creating a booking with a fixed money promo code applied.
	 */
	public function testApplyFixedPromoToBooking(): void
	{
		$this->authAsAdmin();

		$rate = 80.00;
		$tourists = 1;
		$promoFixed = 15.00;
		$baseTotal = $tourists * $rate;
		$subttAfterDiscount = $baseTotal - $promoFixed;

		$setup = $this->createSlotFixture($rate);
		$promo = $this->insertPromo($setup->packageId, [
			'discount_value' => $promoFixed,
			'discount_value_type' => 'Fixed Money',
		]);

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$repo = app(TourBookingRepository::class);
		$result = $repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEqualsWithDelta($promoFixed, (float) $booking->discount2_value, 0.01, 'Fixed discount should be $15');
		$this->assertEquals($promo->code, $booking->discount_promo_code);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	protected function createSlotFixture(float $rate): CreateABooking
	{
		$date = Carbon::today()->addDays(190)->toDateString();
		$builder = (new CreateABooking())
			->withDate($date)
			->withTourists(1)
			->withRate($rate)
			->withSeats(20)
			->withoutTransaction()
			->withoutHistory()
			->withStatus('Booked')
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	protected function buildBookingRequest(CreateABooking $setup, int $tourists, array $overrides = []): Request
	{
		$rate = DB::table('tour_package_rates')->where('id', $setup->rateId)->value('rate');
		$permitFee = DB::table('tour_package_rates')->where('id', $setup->rateId)->value('permit_fee');
		$baseTotal = $tourists * ((float) $rate + (float) $permitFee);

		$request = new Request();
		$request->merge(array_merge([
			'slot_time' => $setup->slotTimeId,
			'affiliatecust' => 0,
			'customer_name' => 'Promo Test',
			'customer_phone_number' => '5550001111',
			'customer_email' => 'promo-' . uniqid() . '@test.com',
			'booked_by_staff' => '',
			'tour_operator_note' => '',
			'is_reserved_for_affiliate' => 0,
			'permit_fee' => [null, $permitFee],
			'rate_group' => [$setup->rateId],
			'tourists' => [$tourists],
			'customer_comment' => '',
			'subtt' => $baseTotal,
			'fee' => 0,
			'cash_paid_amount' => '',
			'payment_method' => 'Cash',
			'dividedBy' => 'Please select option',
			'split_value' => '',
			'custom_fields' => '',
			'custom_charge_data' => '',
			'custom_charge_fees' => 0,
			'pax_addons_data' => '',
			'addons_data' => '',
			'tip_data' => '',
			'hotel_id' => 0,
			'addons' => 0,
		], $overrides));

		return $request;
	}

	// ------------------------------------------------------------------
	// Cleanup
	// ------------------------------------------------------------------

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];

		DB::statement('SET FOREIGN_KEY_CHECKS=0');
		foreach (static::$repoBookingIds as $bookingId) {
			$booking = DB::table('tour_bookings_master')->where('id', $bookingId)->first();
			DB::table('tour_booking_package_swipe_history')->where('tour_booking_id', $bookingId)->delete();
			DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
			DB::table('transactions')->where('tour_booking_id', $bookingId)->delete();
			DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->delete();
			if ($booking) {
				try { DB::table('tour_slot_summary')->where('tour_slot_id', $booking->tour_slot_id)->delete(); } catch (\Exception $e) {}
				DB::table('tour_bookings_master')->where('id', $bookingId)->delete();
				DB::table('customers')->where('id', $booking->customer_id)->delete();
			}
		}
		DB::statement('SET FOREIGN_KEY_CHECKS=1');
		static::$repoBookingIds = [];

		foreach (static::$promoIds as $id) {
			DB::table('tour_promotions')->where('id', $id)->delete();
		}
		static::$promoIds = [];
	}
}
