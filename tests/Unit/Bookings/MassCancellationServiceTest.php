<?php

namespace Tests\Unit\Bookings;

use App\Jobs\ProcessMassCancellationBooking;
use App\Models\Admin;
use App\Models\MassCancellation;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\CancelBooking\MassCancellationService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Mass cancellation validates every selected booking first (all or nothing),
 * then queues a chain of jobs — one per booking — that cancel the bookings
 * through the regular single booking cancel() flow. The first failure stops
 * the run and the remaining bookings are left untouched.
 */
class MassCancellationServiceTest extends TestCase
{
	private array $fixtures = [];

	private int $lastRunId = 0;

	protected function setUp(): void
	{
		parent::setUp();

		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');

		$this->lastRunId = (int) MassCancellation::max('id');
	}

	protected function tearDown(): void
	{
		// A failing test may leave a transaction open; cleaning up inside it would be
		// rolled back and leak the fixtures into the database
		while (DB::transactionLevel() > 0) {
			DB::rollBack();
		}

		MassCancellation::where('id', '>', $this->lastRunId)->delete();

		foreach ($this->fixtures as $fixture) {
			$fixture->cleanup();
		}
		$this->fixtures = [];

		parent::tearDown();
	}

	private function grantAllPermissions(): void
	{
		Gate::before(fn () => true);
	}

	private function booking(string $date, string $from, ?int $packageId = null, string $status = 'Booked'): CreateABooking
	{
		$builder = (new CreateABooking())
			->withTourists(2)
			->withRate(50.00)
			->withPaymentMethod('Cash')
			->withStatus($status)
			->withDate($date)
			->withTimeSlot($from, date('H:i:s', strtotime($from) + 3600));

		$builder = $packageId ? $builder->usePackage($packageId) : $builder->withNewPackage();

		return $this->fixtures[] = $builder->create();
	}

	private function slot(CreateABooking $booking, string $date, string $time = '09:00:00'): array
	{
		return ['date' => $date, 'tour_package_id' => $booking->packageId, 'time_from' => $time];
	}

	/**
	 * Run the queued chain synchronously, in order, like the queue worker would.
	 */
	private function runJobs(int $runId, array $bookingIds): void
	{
		foreach ($bookingIds as $bookingId) {
			(new ProcessMassCancellationBooking($runId, $bookingId))->handle();
		}
	}

	public function test_lists_bookings_of_the_time_slot_with_cancelled_ones_disabled(): void
	{
		$this->grantAllPermissions();
		$date = now()->addDays(20)->toDateString();

		$active = $this->booking($date, '09:00:00');
		$cancelled = $this->booking($date, '09:00:00', $active->packageId, 'Cancelled');
		$otherTime = $this->booking($date, '13:00:00', $active->packageId);

		// Only the selected time slot's bookings; cancelled ones are listed last and can't be selected
		$rows = collect(MassCancellationService::getBookings($date, $active->packageId, '09:00:00'));
		$this->assertSame([$active->bookingId, $cancelled->bookingId], $rows->pluck('id')->all());

		$activeRow = $rows->firstWhere('id', $active->bookingId);
		$this->assertFalse($activeRow['is_cancelled']);
		$this->assertNull($activeRow['blocked_reason']);

		$cancelledRow = $rows->firstWhere('id', $cancelled->bookingId);
		$this->assertTrue($cancelledRow['is_cancelled']);
		$this->assertSame('Already cancelled', $cancelledRow['blocked_reason']);

		$ids = array_column(MassCancellationService::getBookings($date, $active->packageId, '13:00:00'), 'id');
		$this->assertSame([$otherTime->bookingId], $ids);

		$slots = collect(MassCancellationService::getTimeSlots($date, $active->packageId))->keyBy('time_from');
		$this->assertSame(['09:00:00', '13:00:00'], $slots->keys()->all());
		// Counts only active bookings; cancelled ones are counted separately
		$this->assertSame(1, $slots['09:00:00']['bookings_count']);
		$this->assertSame(1, $slots['09:00:00']['cancelled_count']);
	}

	public function test_listed_total_includes_paid_addons_like_the_booking_page(): void
	{
		$this->grantAllPermissions();
		$date = now()->addDays(20)->toDateString();
		$booking = $this->booking($date, '09:00:00');

		$master = DB::table('tour_bookings_master')->where('id', $booking->bookingId)->first(['total', 'booking_total']);
		$base = (float) $master->booking_total != 0.0 ? (float) $master->booking_total : (float) $master->total;

		// A paid add-on is recorded in the transactions ledger, not in tour_bookings_master.total
		DB::table('transactions')->insert([
			'tour_booking_id' => $booking->bookingId,
			'amount' => 32.00,
			'fee_amount' => 0,
			'charge_type' => 'addons',
			'payment_type' => 0,
			'via' => 'Cash',
			'response_init' => '{}',
			'response_end' => '{}',
			'status' => 'Completed',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => now(),
		]);

		$row = collect(MassCancellationService::getBookings($date, $booking->packageId, '09:00:00'))->firstWhere('id', $booking->bookingId);
		$this->assertEqualsWithDelta($base + 32.00, $row['total'], 0.001);
	}

	public function test_listed_paid_amount_deducts_refunds(): void
	{
		$this->grantAllPermissions();
		$date = now()->addDays(20)->toDateString();
		$booking = $this->booking($date, '09:00:00');

		$completed = (float) DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->where('status', 'Completed')->sum('amount');

		// Refunds are stored with a positive amount and status 'Refund'
		DB::table('transactions')->insert([
			'tour_booking_id' => $booking->bookingId,
			'amount' => 30.00,
			'fee_amount' => 0,
			'payment_type' => 0,
			'via' => 'Cash',
			'response_init' => '{}',
			'response_end' => '{}',
			'status' => 'Refund',
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => now(),
		]);

		$row = collect(MassCancellationService::getBookings($date, $booking->packageId, '09:00:00'))->firstWhere('id', $booking->bookingId);
		$this->assertEqualsWithDelta($completed - 30.00, $row['paid'], 0.001);
	}

	public function test_start_run_queues_one_chained_job_per_booking(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$second = $this->booking($date, '09:00:00', $first->packageId);

		$result = MassCancellationService::startRun(
			[$first->bookingId, $second->bookingId],
			['refund_type' => '100_refund', 'comment' => 'Weather closure', 'send_cancel_mail' => false],
			$this->slot($first, $date)
		);

		$this->assertSame(MassCancellation::STATUS_QUEUED, $result['status'], json_encode($result));
		$this->assertSame(2, $result['total']);
		$this->assertSame(['queued', 'queued'], array_column($result['results'], 'status'));

		Bus::assertChained([ProcessMassCancellationBooking::class, ProcessMassCancellationBooking::class]);

		// Nothing is cancelled until the queue processes the jobs
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $first->bookingId)->value('status'));

		// The run remembers who started it, for the job to act as that user
		$run = MassCancellation::find($result['id']);
		$this->assertSame('admin', $run->created_by_guard);
		$this->assertNotNull($run->created_by);
	}

	public function test_jobs_cancel_all_bookings_through_the_regular_cancel_flow(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$second = $this->booking($date, '09:00:00', $first->packageId);
		$ids = [$first->bookingId, $second->bookingId];

		$result = MassCancellationService::startRun(
			$ids,
			['refund_type' => '100_refund', 'comment' => 'Weather closure', 'send_cancel_mail' => false],
			$this->slot($first, $date)
		);

		$this->runJobs($result['id'], $ids);

		$run = MassCancellation::find($result['id']);
		$this->assertSame(MassCancellation::STATUS_COMPLETED, $run->status, json_encode($run->results));
		$this->assertSame(2, $run->cancelled_count);
		$this->assertNotNull($run->finished_at);

		foreach ($ids as $bookingId) {
			$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $bookingId)->value('status'));
			// Same history / refund the single cancel flow writes
			$this->assertTrue(
				DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('operation', 'Cancelled')->exists(),
				'Cancellation must be recorded in history for booking #' . $bookingId
			);
			$this->assertTrue(
				DB::table('transactions')->where('tour_booking_id', $bookingId)->where('status', 'Refund')->where('amount', '>', 0)->exists(),
				'Weather / 100% refund must create a refund transaction for booking #' . $bookingId
			);
		}
	}

	public function test_queued_job_acts_as_the_user_who_started_the_run(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();
		$adminId = Auth::guard('admin')->id();

		$booking = $this->booking($date, '09:00:00');
		$result = MassCancellationService::startRun(
			[$booking->bookingId],
			['refund_type' => '100_refund', 'comment' => 'Weather closure'],
			$this->slot($booking, $date)
		);

		// A queue worker has no logged in user
		Auth::forgetGuards();
		$this->assertNull(Auth::guard('admin')->user());

		$job = new ProcessMassCancellationBooking($result['id'], $booking->bookingId);
		$job->withFakeQueueInteractions(); // as if run by a queue worker
		$job->handle();

		$this->assertSame(MassCancellation::STATUS_COMPLETED, MassCancellation::find($result['id'])->status);
		$this->assertSame('Cancelled', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
		// History is written as the user who started the run, like a cancel from the booking page
		$this->assertSame($adminId, (int) DB::table('tour_booking_history')
			->where('tour_booking_id', $booking->bookingId)
			->where('operation', 'Cancelled')
			->value('operation_by'));

		// The user is not left logged in on the worker
		$this->assertNull(Auth::guard('admin')->user());
	}

	public function test_queues_nothing_when_any_selected_booking_has_an_issue(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$alreadyCancelled = $this->booking($date, '09:00:00', $first->packageId, 'Cancelled');
		$second = $this->booking($date, '09:00:00', $first->packageId);

		$result = MassCancellationService::startRun(
			[$first->bookingId, $alreadyCancelled->bookingId, $second->bookingId],
			['refund_type' => '100_refund', 'comment' => 'Weather closure'],
			$this->slot($first, $date)
		);

		$this->assertSame('validation_failed', $result['status'], json_encode($result));
		$this->assertSame(0, $result['cancelled']);

		// The booking with the issue is reported with its reason
		$results = collect($result['results'])->keyBy('id');
		$this->assertSame('failed', $results[$alreadyCancelled->bookingId]['status']);
		$this->assertSame('Already cancelled.', $results[$alreadyCancelled->bookingId]['message']);
		$this->assertSame('not_processed', $results[$first->bookingId]['status']);

		// Nothing queued, nothing cancelled
		Bus::assertNothingDispatched();
		$this->assertSame(0, MassCancellation::where('id', '>', $this->lastRunId)->count());
		foreach ([$first->bookingId, $second->bookingId] as $bookingId) {
			$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $bookingId)->value('status'));
		}
	}

	public function test_rejects_a_booking_of_another_time_slot(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$otherTime = $this->booking($date, '13:00:00', $first->packageId);

		$result = MassCancellationService::startRun(
			[$first->bookingId, $otherTime->bookingId],
			['refund_type' => 'full_refund', 'comment' => 'test'],
			$this->slot($first, $date)
		);

		$this->assertSame('validation_failed', $result['status']);
		$this->assertSame('Booking not found.', collect($result['results'])->firstWhere('id', $otherTime->bookingId)['message']);
		Bus::assertNothingDispatched();
	}

	public function test_rejects_bookings_already_queued_by_another_run(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$options = ['refund_type' => 'full_refund', 'comment' => 'test'];

		$queued = MassCancellationService::startRun([$first->bookingId], $options, $this->slot($first, $date));
		$this->assertSame(MassCancellation::STATUS_QUEUED, $queued['status']);

		$again = MassCancellationService::startRun([$first->bookingId], $options, $this->slot($first, $date));
		$this->assertSame('validation_failed', $again['status']);
		$this->assertStringContainsString('already being cancelled', $again['results'][0]['message']);
	}

	public function test_stops_at_the_first_booking_that_fails_while_cancelling(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$failing = $this->booking($date, '09:00:00', $first->packageId);
		$third = $this->booking($date, '09:00:00', $first->packageId);
		$ids = [$first->bookingId, $failing->bookingId, $third->bookingId];

		// e.g. Stripe rejecting the refund of the second booking
		$repository = Mockery::mock(TourBookingRepository::class);
		$repository->shouldReceive('cancel')->andReturnUsing(function ($request, $bookingId) use ($failing) {
			if ($bookingId === $failing->bookingId) {
				throw new Exception('Stripe refund failed.');
			}
			DB::table('tour_bookings_master')->where('id', $bookingId)->update(['status' => 'Cancelled']);
		});
		$this->app->instance(TourBookingRepository::class, $repository);

		$result = MassCancellationService::startRun($ids, ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($first, $date));
		$this->runJobs($result['id'], $ids);

		$run = MassCancellation::find($result['id']);
		$this->assertSame(MassCancellation::STATUS_STOPPED, $run->status);
		$this->assertSame(1, $run->cancelled_count);
		$this->assertStringContainsString("#{$failing->bookingId}", $run->message);

		$results = collect($run->results)->keyBy('id');
		$this->assertSame('cancelled', $results[$first->bookingId]['status']);
		$this->assertSame('failed', $results[$failing->bookingId]['status']);
		$this->assertSame('Stripe refund failed.', $results[$failing->bookingId]['message']);
		$this->assertSame('not_processed', $results[$third->bookingId]['status']);

		// The job of the booking after the failing one never touches it
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $third->bookingId)->value('status'));
	}

	public function test_failed_job_stops_the_run(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$second = $this->booking($date, '09:00:00', $first->packageId);
		$ids = [$first->bookingId, $second->bookingId];

		$result = MassCancellationService::startRun($ids, ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($first, $date));

		// e.g. the worker timed out on the first booking
		(new ProcessMassCancellationBooking($result['id'], $first->bookingId))->failed(new Exception('Job timed out'));

		$run = MassCancellation::find($result['id']);
		$this->assertSame(MassCancellation::STATUS_STOPPED, $run->status);
		$this->assertStringContainsString('check this booking manually', collect($run->results)->firstWhere('id', $first->bookingId)['message']);
		$this->assertSame('not_processed', collect($run->results)->firstWhere('id', $second->bookingId)['status']);
	}

	public function test_failed_job_persists_the_stop_when_cancel_left_a_transaction_open(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$second = $this->booking($date, '09:00:00', $first->packageId);
		$ids = [$first->bookingId, $second->bookingId];

		$result = MassCancellationService::startRun($ids, ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($first, $date));

		// A timeout interrupts cancel() mid-way: its transaction is still open (with
		// uncommitted changes) when failed() runs in the same worker process
		DB::beginTransaction();
		DB::table('tour_bookings_master')->where('id', $first->bookingId)->update(['status' => 'Cancelled']);

		(new ProcessMassCancellationBooking($result['id'], $first->bookingId))->failed(new Exception('Job timed out'));

		// The interrupted cancel() is rolled back and the stop is committed, not left
		// inside a transaction that would be discarded when the worker exits
		$this->assertSame(0, DB::transactionLevel());
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $first->bookingId)->value('status'));

		$run = MassCancellation::find($result['id']);
		$this->assertSame(MassCancellation::STATUS_STOPPED, $run->status);
		$this->assertNotNull($run->finished_at);
		$this->assertSame('failed', collect($run->results)->firstWhere('id', $first->bookingId)['status']);
		$this->assertSame('not_processed', collect($run->results)->firstWhere('id', $second->bookingId)['status']);

		// The run is no longer active, so its bookings can be selected again
		$this->assertNull(MassCancellationService::activeRun($date, $first->packageId, '09:00:00'));
	}

	public function test_error_after_the_booking_was_cancelled_records_it_as_cancelled_and_stops(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$first = $this->booking($date, '09:00:00');
		$second = $this->booking($date, '09:00:00', $first->packageId);
		$ids = [$first->bookingId, $second->bookingId];

		// The cancellation + ticket refund are committed, then e.g. the add-on refund fails at Stripe
		$repository = Mockery::mock(TourBookingRepository::class);
		$repository->shouldReceive('cancel')->andReturnUsing(function ($request, $bookingId) use ($first) {
			DB::table('tour_bookings_master')->where('id', $bookingId)->update(['status' => 'Cancelled']);
			if ($bookingId === $first->bookingId) {
				throw new Exception('Add-on refund failed.');
			}
		});
		$this->app->instance(TourBookingRepository::class, $repository);

		$result = MassCancellationService::startRun($ids, ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($first, $date));
		$this->runJobs($result['id'], $ids);

		// Stopped (the error may be a refund that did not go through), but the booking is
		// reported as cancelled — not as failed / not cancelled
		$run = MassCancellation::find($result['id']);
		$this->assertSame(MassCancellation::STATUS_STOPPED, $run->status, json_encode($run->results));
		$this->assertSame(1, $run->cancelled_count);
		$this->assertStringContainsString("#{$first->bookingId}", $run->message);
		$this->assertStringContainsString('check this booking', $run->message);

		$results = collect($run->results)->keyBy('id');
		$this->assertSame('cancelled', $results[$first->bookingId]['status']);
		$this->assertStringContainsString('Add-on refund failed.', $results[$first->bookingId]['message']);
		$this->assertStringContainsString('check this booking', $results[$first->bookingId]['message']);
		$this->assertSame('not_processed', $results[$second->bookingId]['status']);

		// The job of the next booking never touches it
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $second->bookingId)->value('status'));
	}

	/**
	 * Record a "Don't Refund" removal of 1 pax the way the pax removal flows store it:
	 * $0 amount, kept money in refund_keep_us, booking total reduced.
	 */
	private function removeOnePaxWithDontRefund(CreateABooking $booking, string $via, float $kept, float $remainingTotal): void
	{
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $booking->bookingId,
			'tourists' => 1,
			'operation' => 'Refund',
			'payment_type' => $via,
			'payment_method' => "Don't Refund",
			'details' => json_encode(['amount' => 0, 'refund_keep_us' => $kept, 'payment_type' => $via, 'refund_type' => "Don't Refund", 'deleted' => 'Adult person 1']),
			'operation_at' => now(),
			'operation_by' => 1,
			'operation_by_user_type' => 'Admin',
			'created_at' => now(),
		]);
		DB::table('tour_bookings_master')->where('id', $booking->bookingId)->update(['tourists' => 1, 'total' => $remainingTotal]);
	}

	public function test_weather_mass_cancellation_does_not_refund_pax_removed_with_dont_refund(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(24)->toDateString();

		$originalCashFees = DB::table('settings')->where('key', 'CASH_FEES')->value('value');
		DB::table('settings')->where('key', 'CASH_FEES')->update(['value' => '0']);

		$stripeRefunds = [];
		$gateway = Mockery::mock(\App\Contracts\Payments\PaymentGatewayInterface::class);
		$gateway->shouldReceive('refundPayment')->andReturnUsing(function (array $params) use (&$stripeRefunds) {
			$stripeRefunds[] = $params;
			return null;
		});
		$this->app->instance(\App\Contracts\Payments\PaymentGatewayInterface::class, $gateway);

		try {
			// Cash deposit booking: 2 x $50.00 paid, 1 pax removed keeping $50.00
			$cash = $this->booking($date, '09:00:00');
			$this->removeOnePaxWithDontRefund($cash, 'Cash', 50.00, 50.00);

			// Card booking: $170.00 tickets + $12.96 fees, 1 pax removed keeping $91.48
			$card = $this->fixtures[] = (new CreateABooking())
				->withTourists(2)
				->withRate(85.00)
				->withPaymentMethod('Card')
				->withDate($date)
				->withTimeSlot('09:00:00', '10:00:00')
				->usePackage($cash->packageId)
				->create();
			DB::table('tour_bookings_master')->where('id', $card->bookingId)->update(['booking_total' => 182.96, 'service_commission' => 12.96]);
			DB::table('transactions')->where('id', $card->transactionId)->update(['amount' => 182.96]);
			DB::table('tour_booking_history')->where('tour_booking_id', $card->bookingId)->where('operation', 'Payment')->update([
				'ticket_cost' => 170.00,
				'platform_fees' => 12.96,
				'details' => json_encode(['payment_type' => 'Card', 'payment_method' => 'Full Payment', 'amount' => 182.96]),
			]);
			$this->removeOnePaxWithDontRefund($card, 'Card', 91.48, 91.48);

			$ids = [$cash->bookingId, $card->bookingId];
			$result = MassCancellationService::startRun($ids, ['refund_type' => '100_refund', 'comment' => 'Weather closure'], $this->slot($cash, $date));
			$this->assertSame(MassCancellation::STATUS_QUEUED, $result['status'], json_encode($result));

			$this->runJobs($result['id'], $ids);

			$run = MassCancellation::find($result['id']);
			$this->assertSame(MassCancellation::STATUS_COMPLETED, $run->status, json_encode($run->results));
			$this->assertSame(2, $run->cancelled_count);

			// Cash: only the remaining pax's $50.00, not the $100.00 originally paid
			$cashRefund = (float) DB::table('transactions')->where('tour_booking_id', $cash->bookingId)->where('status', 'Refund')->latest('id')->value('amount');
			$this->assertEqualsWithDelta(50.00, $cashRefund, 0.001);

			// Card: Stripe is asked for the remaining pax's $91.48 only, not $182.96
			$this->assertCount(1, $stripeRefunds);
			$this->assertSame(9148, (int) round($stripeRefunds[0]['amount']));
		} finally {
			DB::table('settings')->where('key', 'CASH_FEES')->update(['value' => $originalCashFees]);
		}
	}

	/**
	 * A booking that runs past the queue retry_after is marked failed (stopping the run)
	 * while its job is still running. When that job then finishes the cancellation, the
	 * booking must be recorded as cancelled but the run must stay stopped — the remaining
	 * bookings were never processed, so it must not be reported as completed.
	 */
	public function test_slow_booking_finishing_after_its_job_was_marked_failed_keeps_the_run_stopped(): void
	{
		$this->grantAllPermissions();
		Bus::fake();
		$date = now()->addDays(21)->toDateString();

		$slow = $this->booking($date, '09:00:00');
		$next = $this->booking($date, '09:00:00', $slow->packageId);
		$ids = [$slow->bookingId, $next->bookingId];

		$result = MassCancellationService::startRun($ids, ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($slow, $date));
		$runId = $result['id'];

		$repository = Mockery::mock(TourBookingRepository::class);
		$repository->shouldReceive('cancel')->andReturnUsing(function ($request, $bookingId) use ($runId) {
			// Meanwhile the queue gave up on this job and ran its failed() handler
			(new ProcessMassCancellationBooking($runId, $bookingId))->failed(new Exception('Job has been attempted too many times.'));
			// ...but the slow cancellation still went through
			DB::table('tour_bookings_master')->where('id', $bookingId)->update(['status' => 'Cancelled']);
		});
		$this->app->instance(TourBookingRepository::class, $repository);

		$this->runJobs($runId, $ids);

		$run = MassCancellation::find($runId);
		$this->assertSame(MassCancellation::STATUS_STOPPED, $run->status, $run->message);
		$this->assertSame(1, $run->cancelled_count);
		$this->assertStringContainsString('took longer than expected but was cancelled', $run->message);

		$results = collect($run->results)->keyBy('id');
		$this->assertSame('cancelled', $results[$slow->bookingId]['status']);
		$this->assertSame('not_processed', $results[$next->bookingId]['status']);

		// The next booking's job skipped it
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $next->bookingId)->value('status'));
	}

	public function test_rejects_partial_refund(): void
	{
		$this->grantAllPermissions();
		$date = now()->addDays(22)->toDateString();
		$booking = $this->booking($date, '09:00:00');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('valid refund type');

		MassCancellationService::startRun([$booking->bookingId], ['refund_type' => 'partial_refund', 'comment' => 'test'], $this->slot($booking, $date));
	}

	public function test_rejects_refund_type_without_permission(): void
	{
		$date = now()->addDays(23)->toDateString();
		$booking = $this->booking($date, '09:00:00');

		// Unsaved admin: no super-admin role and no permissions
		$this->actingAs(Admin::factory()->make(), 'admin');

		$caught = null;
		try {
			MassCancellationService::startRun([$booking->bookingId], ['refund_type' => 'full_refund', 'comment' => 'test'], $this->slot($booking, $date));
		} catch (Exception $exception) {
			$caught = $exception;
		}

		$this->assertNotNull($caught, 'Expected a permission exception');
		$this->assertStringContainsString('permission', $caught->getMessage());
		$this->assertSame('Booked', DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'));
	}
}
