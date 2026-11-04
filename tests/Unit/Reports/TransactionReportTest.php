<?php

namespace Tests\Unit\Reports;

use App\Models\Admin;
use App\Repositories\Report\ReportRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class TransactionReportTest extends TestCase
{
	protected static array $builders = [];
	protected static array $extraTransactionIds = [];

	protected function repo(): ReportRepository
	{
		return app(ReportRepository::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Build a report request scoped to a specific date and package.
	 */
	protected function buildReportRequest(string $date, int $packageId, array $overrides = []): Request
	{
		$request = new Request();
		$request->merge(array_merge([
			'viewDate' => 'tourdate',
			'dateFrom' => $date,
			'dateTo' => $date,
			'tourOperatorId' => DB::table('tour_operators')->value('id'),
			'selectedPackage' => $packageId,
			'export' => 0,
			'checkCard' => 'true',
			'checkCash' => 'true',
			'checkInvoiced' => 'true',
		], $overrides));
		return $request;
	}

	/**
	 * Create a booking on the given date with a known rate and tourist count.
	 */
	protected function createBooking(string $date, float $rate, int $tourists = 1, string $paymentMethod = 'Cash'): CreateABooking
	{
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
	 * Test that report totals exactly match the sum of created bookings.
	 *
	 * Creates 3 Cash bookings with known amounts on a future date:
	 *   1 x $50 = $50, 2 x $75 = $150, 3 x $40 = $120 => total $320
	 * Uses baseline/delta to isolate from any pre-existing data.
	 */
	public function testReportTotalsMatchCreatedBookings(): void
	{
		$this->authAsAdmin();

		$date = Carbon::today()->addDays(180)->toDateString();
		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');

		// Capture baseline before creating test bookings
		$baseRequest = $this->buildReportRequest($date, $packageId);
		$baseline = $this->repo()->transactionReport($baseRequest);
		$basePayment = (float) ($baseline->totalPayment ?? 0);
		$baseBookings = (int) ($baseline->totalBookings ?? 0);
		$baseRefund = (float) ($baseline->totalRefund ?? 0);
		$baseNet = (float) ($baseline->totalNet ?? 0);

		// Create 3 bookings with known amounts
		$b1 = $this->createBooking($date, 50.00, 1);  // $50
		$b2 = $this->createBooking($date, 75.00, 2);  // $150
		$b3 = $this->createBooking($date, 40.00, 3);  // $120

		$expectedTotal = 50.00 + 150.00 + 120.00; // $320.00

		$result = $this->repo()->transactionReport($baseRequest);

		$deltaBookings = $result->totalBookings - $baseBookings;
		$deltaPayment = (float) $result->totalPayment - $basePayment;
		$deltaRefund = (float) $result->totalRefund - $baseRefund;
		$deltaNet = (float) $result->totalNet - $baseNet;

		$this->assertEquals(3, $deltaBookings, 'Should add exactly 3 bookings');
		$this->assertEqualsWithDelta($expectedTotal, $deltaPayment, 0.01, 'Payment delta should be $320');
		$this->assertEqualsWithDelta(0, $deltaRefund, 0.01, 'No refund delta expected');
		$this->assertEqualsWithDelta($expectedTotal, $deltaNet, 0.01, 'Net delta should equal payment delta when no refunds or fees');
	}

	/**
	 * Test that refund transactions are subtracted from the net total.
	 *
	 * Creates a $100 booking, adds a $25 refund transaction, and verifies:
	 *   payment delta = $100, refund delta = $25, net delta = $75
	 */
	public function testReportSubtractsRefunds(): void
	{
		$this->authAsAdmin();

		$date = Carbon::today()->addDays(181)->toDateString();
		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');

		// Capture baseline
		$baseRequest = $this->buildReportRequest($date, $packageId);
		$baseline = $this->repo()->transactionReport($baseRequest);
		$basePayment = (float) ($baseline->totalPayment ?? 0);
		$baseRefund = (float) ($baseline->totalRefund ?? 0);
		$baseNet = (float) ($baseline->totalNet ?? 0);

		$booking = $this->createBooking($date, 100.00, 1);

		// Insert a refund transaction for $25
		$refundId = DB::table('transactions')->insertGetId([
			'tour_booking_id' => $booking->bookingId,
			'amount' => 25.00,
			'fee_amount' => 0,
			'transaction_number' => 'REFUND-' . uniqid(),
			'via' => 'Cash',
			'payment_type' => 1,
			'response_init' => '{}',
			'response_end' => '{}',
			'response_end_datetime' => Carbon::now(),
			'status' => 'Refund',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => Carbon::now(),
		]);
		static::$extraTransactionIds[] = $refundId;

		$result = $this->repo()->transactionReport($baseRequest);

		$deltaPayment = (float) $result->totalPayment - $basePayment;
		$deltaRefund = (float) $result->totalRefund - $baseRefund;
		$deltaNet = (float) $result->totalNet - $baseNet;

		$this->assertEqualsWithDelta(100.00, $deltaPayment, 0.01, 'Payment delta should be $100');
		$this->assertEqualsWithDelta(25.00, $deltaRefund, 0.01, 'Refund delta should be $25');
		$this->assertEqualsWithDelta(75.00, $deltaNet, 0.01, 'Net delta should be $100 - $25 = $75');
	}

	/**
	 * Test that the payment method filter correctly isolates transactions.
	 *
	 * Creates one Cash ($60) and one Card ($80) booking on the same date.
	 * Filtering for Cash only should capture $60, Card only should capture $80.
	 */
	public function testReportFiltersByPaymentMethod(): void
	{
		$this->authAsAdmin();

		$date = Carbon::today()->addDays(182)->toDateString();
		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');

		// Capture baselines for each filter
		$cashBaseRequest = $this->buildReportRequest($date, $packageId, [
			'checkCash' => 'true', 'checkCard' => 'false', 'checkInvoiced' => 'false',
		]);
		$cardBaseRequest = $this->buildReportRequest($date, $packageId, [
			'checkCash' => 'false', 'checkCard' => 'true', 'checkInvoiced' => 'false',
		]);

		$cashBaseline = $this->repo()->transactionReport($cashBaseRequest);
		$cardBaseline = $this->repo()->transactionReport($cardBaseRequest);
		$cashBasePayment = (float) ($cashBaseline->totalPayment ?? 0);
		$cardBasePayment = (float) ($cardBaseline->totalPayment ?? 0);

		$this->createBooking($date, 60.00, 1, 'Cash');
		$this->createBooking($date, 80.00, 1, 'Card');

		// Cash only
		$cashResult = $this->repo()->transactionReport($cashBaseRequest);
		$cashDelta = (float) $cashResult->totalPayment - $cashBasePayment;
		$this->assertEqualsWithDelta(60.00, $cashDelta, 0.01, 'Cash filter should add $60');

		// Card only
		$cardResult = $this->repo()->transactionReport($cardBaseRequest);
		$cardDelta = (float) $cardResult->totalPayment - $cardBasePayment;
		$this->assertEqualsWithDelta(80.00, $cardDelta, 0.01, 'Card filter should add $80');
	}

	/**
	 * Test that the report only includes bookings within the requested date range.
	 *
	 * Creates bookings on two different dates and queries for each date individually.
	 */
	public function testReportFiltersByDateRange(): void
	{
		$this->authAsAdmin();

		$dateA = Carbon::today()->addDays(183)->toDateString();
		$dateB = Carbon::today()->addDays(184)->toDateString();
		$packageId = DB::table('tour_packages')->where('status', 'Active')->value('id');

		// Capture baselines
		$reqA = $this->buildReportRequest($dateA, $packageId);
		$reqB = $this->buildReportRequest($dateB, $packageId);
		$baseA = (float) ($this->repo()->transactionReport($reqA)->totalPayment ?? 0);
		$baseB = (float) ($this->repo()->transactionReport($reqB)->totalPayment ?? 0);

		$this->createBooking($dateA, 55.00, 1);
		$this->createBooking($dateB, 70.00, 2); // 2 x $70 = $140

		$deltaA = (float) $this->repo()->transactionReport($reqA)->totalPayment - $baseA;
		$deltaB = (float) $this->repo()->transactionReport($reqB)->totalPayment - $baseB;

		$this->assertEqualsWithDelta(55.00, $deltaA, 0.01, 'DateA should add only $55');
		$this->assertEqualsWithDelta(140.00, $deltaB, 0.01, 'DateB should add only $140 (2 x $70)');
	}

	/**
	 * Test that per-booking data rows contain the expected structure.
	 * DataTables returns stdClass objects for each row.
	 */
	public function testReportDataRowsHaveExpectedFields(): void
	{
		$this->authAsAdmin();

		$date = Carbon::today()->addDays(185)->toDateString();

		$booking = $this->createBooking($date, 90.00, 1);

		$request = $this->buildReportRequest($date, $booking->packageId);
		$result = $this->repo()->transactionReport($request);

		$this->assertNotEmpty($result->data, 'Report should contain at least one data row');

		// Find our specific booking row in the DataTables result
		$row = null;
		foreach ($result->data as $item) {
			$bid = is_object($item) ? $item->bookingId : ($item['bookingId'] ?? null);
			if ($bid == $booking->bookingId) {
				$row = $item;
				break;
			}
		}

		$this->assertNotNull($row, 'Our booking should appear in the report data');

		if (is_object($row)) {
			$this->assertObjectHasProperty('bookingId', $row);
			$this->assertObjectHasProperty('amount', $row);
			$this->assertObjectHasProperty('net_total', $row);
			$this->assertObjectHasProperty('refund', $row);
			$this->assertObjectHasProperty('booking_status', $row);
			$this->assertObjectHasProperty('date', $row);
			$this->assertEquals($booking->bookingId, $row->bookingId);
			$this->assertEqualsWithDelta(90.00, (float) $row->amount, 0.01);
		} else {
			$this->assertArrayHasKey('bookingId', $row);
			$this->assertEquals($booking->bookingId, $row['bookingId']);
			$this->assertEqualsWithDelta(90.00, (float) $row['amount'], 0.01);
		}
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$extraTransactionIds as $id) {
			DB::table('transactions')->where('id', $id)->delete();
		}
		static::$extraTransactionIds = [];

		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];
	}
}
