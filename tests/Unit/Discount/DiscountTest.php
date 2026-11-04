<?php

namespace Tests\Unit\Discount;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DiscountTest extends TestCase
{
	protected TourBookingRepository $repo;
	protected static array $builders = [];
	protected static array $repoBookingIds = [];
	protected static array $promoCodeIds = [];

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
	 * Create the slot/rate infrastructure needed for createBooking() calls.
	 * Returns the CreateABooking builder so tests can access rateId, slotTimeId, packageId.
	 */
	protected function createSlotFixture(float $rate = 50.00, float $permitFee = 0.00): CreateABooking
	{
		$tomorrow = Carbon::tomorrow()->toDateString();
		$builder = (new CreateABooking())
			->withDate($tomorrow)
			->withTourists(1)
			->withRate($rate)
			->withPermitFee($permitFee)
			->withSeats(20)
			->withoutTransaction()
			->withoutHistory()
			->withStatus('Booked')
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	/**
	 * Build a base booking request with dynamic values from the fixture.
	 * Callers can pass overrides for discount-specific fields.
	 */
	protected function buildBookingRequest(CreateABooking $setup, int $tourists, array $overrides = []): Request
	{
		$rate = DB::table('tour_package_rates')->where('id', $setup->rateId)->value('rate');
		$permitFee = DB::table('tour_package_rates')->where('id', $setup->rateId)->value('permit_fee');
		$baseTotal = $tourists * ((float) $rate + (float) $permitFee);

		$request = new Request();
		$request->merge(array_merge([
			'slot_time' => $setup->slotTimeId,
			'affiliatecust' => 0,
			'customer_name' => 'Discount Test',
			'customer_phone_number' => '5550001111',
			'customer_email' => 'discount-' . uniqid() . '@test.com',
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

	/**
	 * Create a TourPromotion for the given package.
	 * Uses DB::table() to bypass the model's creating event which
	 * overrides tour_package_id from the HTTP request context.
	 */
	protected function createPromoCode(int $packageId, string $code, float $value, string $valueType): object
	{
		$tourOperatorId = DB::table('tour_operators')->value('id') ?? 1;
		$now = Carbon::now();
		$id = DB::table('tour_promotions')->insertGetId([
			'tour_operator_id' => $tourOperatorId,
			'tour_package_id' => $packageId,
			'code' => $code,
			'max_allowed_usages' => 0,
			'min_allowed_tourists' => 0,
			'start_date' => $now->copy()->subDay()->toDateString(),
			'end_date' => $now->copy()->addYear()->toDateString(),
			'is_extra_charge' => 0,
			'discount_value' => $value,
			'discount_value_type' => $valueType,
			'status' => 'Active',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		static::$promoCodeIds[] = $id;
		return DB::table('tour_promotions')->find($id);
	}

	/**
	 * Test applying a percentage discount to a booking.
	 * Verifies the discount amount is calculated correctly and stored.
	 */
	public function testApplyPercentageDiscountInBooking(): void
	{
		$this->authAsAdmin();

		$rate = 50.00;
		$tourists = 2;
		$discountPct = 10;
		$baseTotal = $tourists * $rate;
		$discountAmt = round($baseTotal * $discountPct / 100, 2);
		$subttAfterDiscount = $baseTotal - $discountAmt;

		$setup = $this->createSlotFixture($rate);

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Percentage',
			'discount_percentage' => $discountPct,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$result = $this->repo->createBooking($request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('bookingId', $result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEquals($discountAmt, (float) $booking->discount2_value, 'Discount amount should be 10% of base total');
		$this->assertEquals($discountPct, (float) $booking->discount2_percentage, 'Discount percentage should be stored');
	}

	/**
	 * Test applying a fixed money discount to a booking.
	 * Verifies the fixed discount amount is stored correctly.
	 */
	public function testApplyFixedMoneyDiscountInBooking(): void
	{
		$this->authAsAdmin();

		$rate = 50.00;
		$tourists = 2;
		$discountAmt = 10.00;
		$baseTotal = $tourists * $rate;
		$subttAfterDiscount = $baseTotal - $discountAmt;

		$setup = $this->createSlotFixture($rate);

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Fixed Money',
			'discount_fixed_money' => $discountAmt,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$result = $this->repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEquals($discountAmt, (float) $booking->discount2_value, 'Fixed discount amount should be stored');
	}

	/**
	 * Test applying a promo code with percentage discount.
	 * Verifies the promo code is looked up and discount calculated from its value.
	 */
	public function testApplyPromoCodePercentageDiscountInBooking(): void
	{
		$this->authAsAdmin();

		$rate = 50.00;
		$tourists = 2;
		$promoDiscountPct = 10;
		$baseTotal = $tourists * $rate;
		$discountAmt = round($baseTotal * $promoDiscountPct / 100, 2);
		$subttAfterDiscount = $baseTotal - $discountAmt;

		$setup = $this->createSlotFixture($rate);
		$promo = $this->createPromoCode($setup->packageId, 'PCT-' . uniqid(), $promoDiscountPct, 'Percent');

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$result = $this->repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEquals($discountAmt, (float) $booking->discount2_value, 'Promo percentage discount should be calculated correctly');
		$this->assertEquals($promo->code, $booking->discount_promo_code, 'Promo code should be stored on booking');
	}

	/**
	 * Test applying a promo code with fixed money discount.
	 * Verifies the fixed promo discount amount is stored correctly.
	 */
	public function testApplyPromoCodeFixedMoneyDiscountInBooking(): void
	{
		$this->authAsAdmin();

		$rate = 50.00;
		$tourists = 2;
		$promoDiscountAmt = 15.00;
		$baseTotal = $tourists * $rate;
		$subttAfterDiscount = $baseTotal - $promoDiscountAmt;

		$setup = $this->createSlotFixture($rate);
		$promo = $this->createPromoCode($setup->packageId, 'FIX-' . uniqid(), $promoDiscountAmt, 'Fixed Money');

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Promo Code',
			'discount_promo_code' => $promo->code,
			'is_applied' => 1,
			'subtt' => $subttAfterDiscount,
		]);

		$result = $this->repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals('Booked', $booking->status);
		$this->assertEquals($promoDiscountAmt, (float) $booking->discount2_value, 'Promo fixed discount should match promo value');
		$this->assertEquals($promo->code, $booking->discount_promo_code, 'Promo code should be stored on booking');
	}

	/**
	 * Test that an invalid promo code results in no discount being applied.
	 * createBooking silently ignores invalid codes rather than throwing.
	 */
	public function testInvalidPromoCodeAppliesNoDiscount(): void
	{
		$this->authAsAdmin();

		$rate = 50.00;
		$tourists = 1;
		$baseTotal = $tourists * $rate;

		$setup = $this->createSlotFixture($rate);

		$request = $this->buildBookingRequest($setup, $tourists, [
			'discount_type' => 'Promo Code',
			'discount_promo_code' => 'ZZ' . substr(uniqid(), -8),
			'is_applied' => 1,
			'subtt' => $baseTotal,
		]);

		$result = $this->repo->createBooking($request);

		$this->assertIsArray($result);
		$bookingId = $result['bookingId'];
		static::$repoBookingIds[] = $bookingId;

		$booking = TourBooking::find($bookingId);
		$this->assertNotNull($booking);
		$this->assertEquals(0.00, (float) $booking->discount2_value, 'Invalid promo code should result in zero discount');
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];

		DB::statement('SET FOREIGN_KEY_CHECKS=0');
		foreach (static::$repoBookingIds as $bookingId) {
			$booking = DB::table('tour_bookings_master')->where('id', $bookingId)->first();
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

		foreach (static::$promoCodeIds as $id) {
			DB::table('tour_promotions')->where('id', $id)->delete();
		}
		static::$promoCodeIds = [];
	}
}
