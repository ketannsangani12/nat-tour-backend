<?php

namespace Tests\Unit\Invoices;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\Invoice;
use App\Repositories\Invoice\InvoiceRepository;
use App\Http\Controllers\Admin\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceTest extends TestCase
{
	protected InvoiceRepository $repo;
	protected InvoiceController $controller;
	protected static array $builders = [];
	protected static array $invoiceUuids = [];
	protected static ?int $affiliateCustomerId = null;

	public function setUp(): void
	{
		parent::setUp();
		$this->repo = app(InvoiceRepository::class);
		$this->controller = app(InvoiceController::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Get or create an affiliate customer for invoice tests.
	 */
	protected function getOrCreateAffiliateCustomer(): int
	{
		if (static::$affiliateCustomerId) {
			return static::$affiliateCustomerId;
		}

		$tourOperatorId = DB::table('tour_operators')->value('id') ?? 1;
		static::$affiliateCustomerId = DB::table('customers')->insertGetId([
			'tour_operator_id' => $tourOperatorId,
			'name' => 'Invoice Test Affiliate',
			'email' => 'invoice-affiliate-' . uniqid() . '@test.com',
			'phone_number' => '5550008888',
			'is_affiliate' => 'Yes',
			'status' => 'Active',
			'remember_token' => '',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => Carbon::now(),
		]);
		return static::$affiliateCustomerId;
	}

	/**
	 * Create an affiliate booking with payment method 'Invoiced'.
	 */
	protected function createAffiliateBooking(float $rate, int $tourists = 1, ?string $date = null): CreateABooking
	{
		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$date = $date ?? Carbon::tomorrow()->toDateString();

		$builder = (new CreateABooking())
			->withDate($date)
			->withTourists($tourists)
			->withRate($rate)
			->withPaymentMethod('Invoiced')
			->useCustomer($affiliateId)
			->create();
		static::$builders[] = $builder;
		return $builder;
	}

	/**
	 * Create an Invoice record via DB::table to bypass model boot events
	 * that depend on full HTTP context.
	 */
	protected function createInvoice(int $affiliateId, string $fromDate, string $toDate, float $amount = 0): object
	{
		$tourOperatorId = DB::table('tour_operators')->value('id') ?? 1;
		$now = Carbon::now();
		$uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();

		$latestInvoice = DB::table('invoices')->orderBy('id', 'desc')->first();
		$nextNum = $latestInvoice
			? 'NAT' . sprintf('%07d', (int) preg_replace('/[^0-9]/', '', $latestInvoice->invoice_number) + 1)
			: 'NAT0000001';

		$id = DB::table('invoices')->insertGetId([
			'uuid' => $uuid,
			'tour_operator_id' => $tourOperatorId,
			'customer_id' => $affiliateId,
			'invoice_number' => $nextNum,
			'from_date' => $fromDate,
			'to_date' => $toDate,
			'due_date' => Carbon::parse($toDate)->addDays(30)->toDateString(),
			'amount' => $amount,
			'status' => 'draft',
			'email_sent' => 0,
			'created_by' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		]);

		static::$invoiceUuids[] = $uuid;
		return DB::table('invoices')->find($id);
	}

	// -----------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------

	/**
	 * Test that an invoice can be created with auto-generated UUID and
	 * invoice number, then soft-deleted.
	 */
	public function testCanCreateAndDeleteInvoice(): void
	{
		$this->authAsAdmin();

		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$today = Carbon::today()->toDateString();

		$invoice = Invoice::create([
			'tour_operator_id' => $tourOperatorId,
			'customer_id' => $affiliateId,
			'from_date' => $today,
			'to_date' => $today,
			'due_date' => Carbon::today()->addDays(30)->toDateString(),
			'amount' => 500.00,
		]);
		static::$invoiceUuids[] = $invoice->uuid;

		$this->assertNotNull($invoice);
		$this->assertNotEmpty($invoice->uuid);
		$this->assertStringStartsWith('NAT', $invoice->invoice_number);
		$this->assertEquals(500.00, (float) $invoice->amount);

		$invoice->status = 'draft';
		$invoice->save();
		$this->assertEquals('draft', $invoice->status);

		$invoice->delete();
		$this->assertNotNull($invoice->deleted_at, 'Invoice should be soft-deleted');
	}

	/**
	 * Test that hasStripeInvoice returns false when no external ID is set.
	 */
	public function testHasStripeInvoiceReturnsFalseWithoutExternalId(): void
	{
		$this->authAsAdmin();

		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$tourOperatorId = DB::table('tour_operators')->value('id');
		$today = Carbon::today()->toDateString();

		$invoice = Invoice::create([
			'tour_operator_id' => $tourOperatorId,
			'customer_id' => $affiliateId,
			'from_date' => $today,
			'to_date' => $today,
			'due_date' => $today,
			'amount' => 100.00,
		]);
		static::$invoiceUuids[] = $invoice->uuid;

		$invoiceWithRelations = Invoice::uuid($invoice->uuid)
			->with(['tourOperator', 'affiliate'])
			->first();

		$result = $this->repo->hasStripeInvoice($invoiceWithRelations);
		$this->assertFalse($result, 'Invoice without external_invoice_id should not be flagged as having Stripe');
	}

	/**
	 * Test that isInDraftState correctly identifies draft-like statuses.
	 */
	public function testIsInDraftState(): void
	{
		$this->assertTrue($this->repo->isInDraftState('draft'));
		$this->assertTrue($this->repo->isInDraftState('editable'));
		$this->assertTrue($this->repo->isInDraftState('init'));
		$this->assertFalse($this->repo->isInDraftState('sent'));
		$this->assertFalse($this->repo->isInDraftState('paid'));
	}

	/**
	 * Test creating multiple affiliate bookings and verifying that the
	 * invoice total (computed from tour_booking_history) matches the
	 * sum of individual booking amounts.
	 *
	 * This is the critical accounting test: the bookkeeper needs
	 * invoiceSumByBookings() to return the exact net payment total.
	 */
	public function testInvoiceSumMatchesAffiliateBookingTotals(): void
	{
		$this->authAsAdmin();

		$tomorrow = Carbon::tomorrow()->toDateString();

		// Create 3 affiliate bookings with known rates
		$booking1 = $this->createAffiliateBooking(50.00, 1, $tomorrow);  // $50
		$booking2 = $this->createAffiliateBooking(75.00, 2, $tomorrow);  // $150
		$booking3 = $this->createAffiliateBooking(100.00, 1, $tomorrow); // $100

		$expectedTotal = 50.00 + 150.00 + 100.00; // $300

		$bookingIds = [
			$booking1->bookingId,
			$booking2->bookingId,
			$booking3->bookingId,
		];

		// Verify each booking has a Payment history entry with correct ticket_cost
		foreach ([
			$booking1->bookingId => 50.00,
			$booking2->bookingId => 150.00,
			$booking3->bookingId => 100.00,
		] as $bookingId => $expectedAmount) {
			$history = DB::table('tour_booking_history')
				->where('tour_booking_id', $bookingId)
				->where('operation', 'Payment')
				->first();
			$this->assertNotNull($history, "Booking {$bookingId} should have a Payment history entry");
			$this->assertEquals($expectedAmount, (float) $history->ticket_cost, "Booking {$bookingId} ticket_cost should match rate x tourists");
		}

		// Create invoice and link bookings
		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$invoice = $this->createInvoice($affiliateId, $tomorrow, $tomorrow, $expectedTotal);

		foreach ($bookingIds as $bookingId) {
			DB::table('invoice_bookings')->insert([
				'tour_booking_id' => $bookingId,
				'invoice_id' => $invoice->id,
				'created_by' => 1,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			]);
		}

		// Use the same calculation the controller uses for invoice totals
		$calculatedSum = $this->controller->invoiceSumByBookings($bookingIds);
		$this->assertEquals($expectedTotal, $calculatedSum, 'invoiceSumByBookings should equal sum of booking amounts ($300)');

		// Verify invoice_bookings count
		$linkedCount = DB::table('invoice_bookings')
			->where('invoice_id', $invoice->id)
			->count();
		$this->assertEquals(3, $linkedCount, 'Invoice should have 3 linked bookings');
	}

	/**
	 * Test that partial refunds are subtracted from the invoice sum.
	 * Bookkeeper needs to see that refunds reduce the invoiced amount.
	 */
	public function testInvoiceSumSubtractsRefunds(): void
	{
		$this->authAsAdmin();

		$tomorrow = Carbon::tomorrow()->toDateString();

		// Create an affiliate booking for $100
		$booking = $this->createAffiliateBooking(100.00, 1, $tomorrow);

		// Simulate a $25 refund by adding a Refund history entry
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $booking->bookingId,
			'transaction_id' => 0,
			'tourists' => 1,
			'operation' => 'Refund',
			'ticket_cost' => 25.00,
			'discount' => 0,
			'permit_fees' => 0,
			'processing_fees' => 0,
			'payment_type' => 'Cash',
			'payment_method' => 'Cash',
			'details' => json_encode([
				'payment_type' => 'Cash',
				'payment_method' => 'Partial Refund',
				'created_by_with_name' => 'Test: (Admin)',
				'amount' => 25.00,
			]),
			'operation_at' => Carbon::now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => Carbon::now(),
		]);

		$calculatedSum = $this->controller->invoiceSumByBookings([$booking->bookingId]);
		$this->assertEquals(75.00, $calculatedSum, 'Invoice sum should be $100 payment minus $25 refund = $75');
	}

	/**
	 * Test that mapBookingsWithInvoice finds affiliate bookings with
	 * Invoiced transactions in the given date range.
	 */
	public function testMapBookingsWithInvoiceFindsAffiliateBookings(): void
	{
		$this->authAsAdmin();

		$tomorrow = Carbon::tomorrow()->toDateString();
		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$tourOperatorId = DB::table('tour_operators')->value('id');

		$booking = $this->createAffiliateBooking(60.00, 1, $tomorrow);
		$invoice = $this->createInvoice($affiliateId, $tomorrow, $tomorrow);

		$request = new Request();
		$request->merge([
			'from_date' => $tomorrow,
			'to_date' => $tomorrow,
			'customer_id' => $affiliateId,
			'tour_operator_id' => $tourOperatorId,
			'tour_package' => 0,
		]);

		$result = $this->controller->mapBookingsWithInvoice($invoice->id, $request);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('count', $result);
		$this->assertArrayHasKey('ids', $result);
		$this->assertGreaterThanOrEqual(1, $result['count'], 'Should find at least 1 affiliate booking');

		// Verify our booking was linked
		$linkedBookingIds = array_column($result['ids'], 'bookingid');
		$this->assertContains($booking->bookingId, $linkedBookingIds, 'Our affiliate booking should be mapped to the invoice');
	}

	/**
	 * Test listing invoices for a date range returns results.
	 */
	public function testCanListInvoicesForDateRange(): void
	{
		$this->authAsAdmin();

		$affiliateId = $this->getOrCreateAffiliateCustomer();
		$tomorrow = Carbon::tomorrow()->toDateString();

		// Create an invoice with a linked booking so the join in list() works
		$booking = $this->createAffiliateBooking(50.00, 1, $tomorrow);
		$invoice = $this->createInvoice($affiliateId, $tomorrow, $tomorrow, 50.00);
		DB::table('invoice_bookings')->insert([
			'tour_booking_id' => $booking->bookingId,
			'invoice_id' => $invoice->id,
			'created_by' => 1,
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]);

		$request = new Request();
		$request->merge([
			'from_date' => $tomorrow,
			'to_date' => $tomorrow,
		]);

		$response = $this->controller->list($request, $affiliateId);
		$data = json_decode($response->getContent(), true);

		$this->assertArrayHasKey('results', $data);
		$this->assertNull($data['error']);

		$invoiceIds = array_column($data['results'], 'id');
		$this->assertContains($invoice->id, $invoiceIds, 'The created invoice should appear in the list');
	}

	public static function tearDownAfterClass(): void
	{
		// Clean up builders (bookings + related records)
		foreach (static::$builders as $builder) {
			$builder->cleanup();
		}
		static::$builders = [];

		// Clean up invoices and invoice_bookings
		foreach (static::$invoiceUuids as $uuid) {
			$inv = DB::table('invoices')->where('uuid', $uuid)->first();
			if ($inv) {
				DB::table('invoice_bookings')->where('invoice_id', $inv->id)->delete();
				DB::table('invoices')->where('id', $inv->id)->delete();
			}
		}
		static::$invoiceUuids = [];

		// Clean up affiliate customer
		if (static::$affiliateCustomerId) {
			DB::table('customers')->where('id', static::$affiliateCustomerId)->delete();
			static::$affiliateCustomerId = null;
		}
	}
}
