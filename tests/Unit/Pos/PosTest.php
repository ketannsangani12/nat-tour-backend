<?php

namespace Tests\Unit\Pos;

use App\Models\Admin;
use App\Repositories\Pos\PosRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class PosTest extends TestCase
{
	protected static array $builders = [];
	protected static array $promoCodeIds = [];
	protected static ?int $groupId = null;

	public function setUp(): void
	{
		parent::setUp();
	}

	protected function repo(): PosRepository
	{
		return app(PosRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Create a booking with a group_id to simulate a POS order.
	 */
	protected function createPosOrderBooking(float $rate = 50.00, int $tourists = 1): CreateABooking
	{
		if (!static::$groupId) {
			$maxGroup = DB::table('tour_bookings_master')->max('group_id') ?? 0;
			static::$groupId = $maxGroup + 9000;
		}

		$builder = (new CreateABooking())
			->withDate(Carbon::tomorrow()->toDateString())
			->withTourists($tourists)
			->withRate($rate)
			->withPaymentMethod('Cash')
			->withBookingOverrides(['group_id' => static::$groupId])
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	/**
	 * Test that initPos returns the expected page structure.
	 */
	public function testCanInitPos(): void
	{
		$this->authAsAdmin();

		$result = $this->repo()->initPos();

		$this->assertIsArray($result);
		$this->assertEquals('POS', $result['page_title']);
		$this->assertArrayHasKey('categories', $result);
		$this->assertArrayHasKey('defaultCategoryId', $result);
		$this->assertArrayHasKey('cashFees', $result);
		$this->assertArrayHasKey('currentTime', $result);
	}

	/**
	 * Test that getPackages returns tour packages for a valid category.
	 */
	public function testCanGetPackages(): void
	{
		$this->authAsAdmin();

		$categoryId = DB::table('categories')->where('status', 'Active')->value('id');
		$this->assertNotNull($categoryId, 'At least one active category must exist');

		$request = new Request();
		$request->initialize([], [], [], [], [],
			['CONTENT_TYPE' => 'application/json'],
			json_encode(['tourPackages' => []])
		);

		$response = $this->repo()->getPackages($categoryId, $request);

		$data = json_decode($response->getContent(), true);
		$this->assertArrayHasKey('tourPackages', $data);
		$this->assertIsArray($data['tourPackages']);

		if (count($data['tourPackages']) > 0) {
			$first = $data['tourPackages'][0];
			$this->assertArrayHasKey('tourPackageName', $first);
			$this->assertArrayHasKey('tourPackageId', $first);
			$this->assertArrayHasKey('rategroups', $first);
		}
	}

	/**
	 * Test that applyPromoCode returns discount info for a valid code.
	 */
	public function testCanApplyValidPromoCode(): void
	{
		$this->authAsAdmin();

		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');
		$this->assertNotNull($packageId);

		$tourOperatorId = DB::table('tour_operators')->value('id') ?? 1;
		$now = Carbon::now();
		$code = 'PT' . substr(uniqid(), -8);

		$promoId = DB::table('tour_promotions')->insertGetId([
			'tour_operator_id' => $tourOperatorId,
			'tour_package_id' => $packageId,
			'code' => $code,
			'max_allowed_usages' => 0,
			'min_allowed_tourists' => 0,
			'start_date' => $now->copy()->subDay()->toDateString(),
			'end_date' => $now->copy()->addYear()->toDateString(),
			'is_extra_charge' => 0,
			'discount_value' => 15,
			'discount_value_type' => 'Percent',
			'status' => 'Active',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
			'updated_at' => $now,
		]);
		static::$promoCodeIds[] = $promoId;

		$request = new Request();
		$request->merge([
			'tourPackageId' => $packageId,
			'promoCode' => $code,
		]);

		$result = $this->repo()->applyPromoCode($request);

		$this->assertIsArray($result);
		$this->assertEquals('Percent', $result['discountType']);
		$this->assertEquals(15, (float) $result['discount']);
		$this->assertEquals($code, $result['promoCode']);
	}

	/**
	 * Test that applyPromoCode rejects an invalid code.
	 */
	public function testApplyInvalidPromoCodeReturnsError(): void
	{
		$this->authAsAdmin();

		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');

		$request = new Request();
		$request->merge([
			'tourPackageId' => $packageId,
			'promoCode' => 'ZZ' . substr(uniqid(), -8),
		]);

		$result = $this->repo()->applyPromoCode($request);

		// sendJsonErrMsg returns a JsonResponse with an error message
		$this->assertNotNull($result);
		$data = json_decode($result->getContent(), true);
		$this->assertTrue(
			!empty($data['error']) || !empty($data['errors']),
			'Invalid promo code should return an error response'
		);
	}

	/**
	 * Test that orders() returns the expected structure.
	 */
	public function testCanGetOrders(): void
	{
		$this->authAsAdmin();

		$result = $this->repo()->orders();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('orders', $result);
		$this->assertIsArray($result['orders']);
	}

	/**
	 * Test that order() returns detailed info for a POS order
	 * identified by group_id.
	 */
	public function testCanGetOrderInfo(): void
	{
		$this->authAsAdmin();

		$this->createPosOrderBooking(50.00, 2);

		$result = $this->repo()->order(static::$groupId);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('bookingsInfo', $result);
		$this->assertArrayHasKey('orderId', $result);
		$this->assertArrayHasKey('paymentMethod', $result);
		$this->assertArrayHasKey('customerName', $result);
		$this->assertEquals(static::$groupId, $result['orderId']);
		$this->assertNotEmpty($result['bookingsInfo'], 'Order should contain at least one booking');
	}

	/**
	 * Test that printReceipt returns the print view data.
	 */
	public function testCanPrintReceipt(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('imagick extension is not available');
		}

		$this->authAsAdmin();

		// Ensure the POS order booking exists (may have been created by prior test)
		if (!static::$groupId) {
			$this->createPosOrderBooking(50.00, 1);
		}

		$result = $this->repo()->printReceipt(static::$groupId);

		$this->assertIsArray($result);
		$this->assertEquals('Print Receipt', $result['page_title']);
		$this->assertArrayHasKey('orderId', $result);
		$this->assertEquals(static::$groupId, $result['orderId']);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];

		foreach (static::$promoCodeIds as $id) {
			DB::table('tour_promotions')->where('id', $id)->delete();
		}
		static::$promoCodeIds = [];

		static::$groupId = null;
	}
}
