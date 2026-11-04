<?php

namespace Tests\Unit;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Dispute;
use App\Repositories\Dispute\DisputeRepository;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\EvidenceCompilerService;
use App\Services\Dispute\StripeErrorHandler;
use App\Services\Dispute\DisputeAuditLogger;
use App\Services\Dispute\DisputeNotificationService;
use Stripe\StripeClient;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Mockery;

class DisputeServiceEnhancedTest extends TestCase
{
    protected $disputeRepository;
    protected $evidenceCompiler;
    protected $stripeErrorHandler;
    protected $disputeService;

    /**
     * Dispute::factory()->create() without explicit booking_id/transaction_id
     * cascades a fresh TourBooking + Transaction row via DisputeFactory's
     * definition(). tests/TestCase.php runs against the real tenant DB with no
     * transaction/rollback wrapper, so these ids must be tracked here and
     * cleaned up explicitly in tearDown().
     */
    protected array $createdDisputeIds = [];
    protected array $createdBookingIds = [];
    protected array $createdTransactionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->disputeRepository = Mockery::mock(DisputeRepository::class);
        $this->evidenceCompiler = Mockery::mock(EvidenceCompilerService::class);
        $this->stripeErrorHandler = Mockery::mock(StripeErrorHandler::class);

        $auditLogger = Mockery::mock(DisputeAuditLogger::class)->shouldIgnoreMissing();
        $notificationService = Mockery::mock(DisputeNotificationService::class)->shouldIgnoreMissing();

        $this->disputeService = new DisputeService(
            $this->disputeRepository,
            $this->evidenceCompiler,
            $this->stripeErrorHandler,
            $auditLogger,
            $notificationService
        );
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdDisputeIds)) {
            DB::table('disputes')->whereIn('id', $this->createdDisputeIds)->delete();
        }
        if (!empty($this->createdTransactionIds)) {
            DB::table('transactions')->whereIn('id', $this->createdTransactionIds)->delete();
        }
        if (!empty($this->createdBookingIds)) {
            DB::table('transactions')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_transact')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_booking_history')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_master')->whereIn('id', $this->createdBookingIds)->delete();
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Track every id a bare Dispute::factory()->create() call cascades:
     * the dispute itself, its booking_id, its transaction_id, AND the
     * SEPARATE booking that TransactionFactory's own default
     * 'tour_booking_id' => TourBooking::factory() independently creates
     * (it does not reuse the dispute's booking_id unless told to).
     */
    protected function trackDisputeCascade(Dispute $dispute): void
    {
        $this->createdDisputeIds[] = $dispute->id;
        $this->createdBookingIds[] = $dispute->booking_id;
        $this->createdTransactionIds[] = $dispute->transaction_id;

        $transactionBookingId = DB::table('transactions')->where('id', $dispute->transaction_id)->value('tour_booking_id');
        if ($transactionBookingId) {
            $this->createdBookingIds[] = $transactionBookingId;
        }
    }

    /** @test */
    public function sync_with_stripe_detects_and_logs_changes()
    {
        $originalDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_sync_detect_' . uniqid(),
            'status' => 'warning_needs_response',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(7),
        ]);
        $this->trackDisputeCascade($originalDispute);

        $updatedDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_sync_updated_' . uniqid(),
            'status' => 'under_review',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(5),
        ]);
        $this->trackDisputeCascade($updatedDispute);

        $stripeData = [
            'id' => $originalDispute->stripe_dispute_id,
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'under_review',
            'evidence_details' => ['due_by' => time() + (5 * 24 * 60 * 60)],
            'created' => time(),
        ];

        $this->stripeErrorHandler
            ->shouldReceive('executeWithErrorHandling')
            ->andReturn($stripeData);

        $this->disputeRepository
            ->shouldReceive('findById')
            ->with($originalDispute->id)
            ->andReturn($originalDispute);

        $this->disputeRepository
            ->shouldReceive('update')
            ->with($originalDispute->id, Mockery::type('array'))
            ->andReturn($updatedDispute);

        $result = $this->disputeService->syncWithStripe($originalDispute->id);

        $this->assertInstanceOf(Dispute::class, $result);
        $this->assertEquals('under_review', $result->status);
    }

    /** @test */
    public function sync_with_stripe_handles_api_errors_gracefully()
    {
        $originalDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_api_error_' . uniqid(),
            'status' => 'warning_needs_response',
        ]);
        $this->trackDisputeCascade($originalDispute);

        $this->disputeRepository
            ->shouldReceive('findById')
            ->with($originalDispute->id)
            ->andReturn($originalDispute);

        // Simulate the stripe error handler propagating an API error as a plain Exception
        $this->stripeErrorHandler
            ->shouldReceive('executeWithErrorHandling')
            ->andThrow(new \Exception('Stripe API error: Rate limit exceeded'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe API error: Rate limit exceeded');

        $this->disputeService->syncWithStripe($originalDispute->id);
    }

    /** @test */
    public function sync_with_stripe_logs_no_changes_when_data_identical()
    {
        $originalDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_no_changes_' . uniqid(),
            'status' => 'warning_needs_response',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(7),
        ]);
        $this->trackDisputeCascade($originalDispute);

        $stripeData = [
            'id' => $originalDispute->stripe_dispute_id,
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_details' => ['due_by' => $originalDispute->evidence_due_by->timestamp],
            'created' => time(),
        ];

        $this->stripeErrorHandler
            ->shouldReceive('executeWithErrorHandling')
            ->andReturn($stripeData);

        $this->disputeRepository
            ->shouldReceive('findById')
            ->with($originalDispute->id)
            ->andReturn($originalDispute);

        $this->disputeRepository
            ->shouldReceive('update')
            ->with($originalDispute->id, Mockery::type('array'))
            ->andReturn($originalDispute);

        $result = $this->disputeService->syncWithStripe($originalDispute->id);

        $this->assertInstanceOf(Dispute::class, $result);
        $this->assertEquals('warning_needs_response', $result->status);
    }

    /** @test */
    public function sync_with_stripe_works_with_stripe_dispute_id()
    {
        $originalDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_by_stripe_id_' . uniqid(),
            'status' => 'warning_needs_response',
        ]);
        $this->trackDisputeCascade($originalDispute);

        $stripeData = [
            'id' => $originalDispute->stripe_dispute_id,
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_details' => ['due_by' => time() + (7 * 24 * 60 * 60)],
            'created' => time(),
        ];

        $this->stripeErrorHandler
            ->shouldReceive('executeWithErrorHandling')
            ->andReturn($stripeData);

        $this->disputeRepository
            ->shouldReceive('findByStripeId')
            ->with($originalDispute->stripe_dispute_id)
            ->andReturn($originalDispute);

        $this->disputeRepository
            ->shouldReceive('update')
            ->with($originalDispute->id, Mockery::type('array'))
            ->andReturn($originalDispute);

        $result = $this->disputeService->syncWithStripe($originalDispute->stripe_dispute_id);

        $this->assertInstanceOf(Dispute::class, $result);
    }

    /** @test */
    public function sync_with_stripe_throws_exception_for_nonexistent_dispute()
    {
        $this->disputeRepository
            ->shouldReceive('findById')
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dispute not found: 999');

        $this->disputeService->syncWithStripe(999);
    }

    // -----------------------------------------------------------------
    // Payout Simplification: handleDisputeLoss()'s payment_mode branch.
    // A platform_held charge never carries transfer_data/transfer at all (the
    // whole point of that settlement model), so its dispute-loss reversal must
    // be skipped as an EXPECTED outcome (info log), not treated as the same
    // anomaly a destination charge missing its transfer would be (error log).
    // -----------------------------------------------------------------

    /** @test */
    public function handle_dispute_loss_skips_reversal_for_platform_held_charge_and_logs_expected_outcome()
    {
        $dispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_platform_held_' . uniqid(),
            'status' => 'lost',
        ]);
        $this->trackDisputeCascade($dispute);

        // A platform-held charge: no transfer_data, no transfer — exactly what
        // PaymentModeInference::fromStripeObject() reads as PLATFORM_HELD.
        $charge = (object) [
            'id' => 'ch_platform_held_test',
            'transfer_data' => null,
            'transfer' => null,
        ];

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('retrieveCharge')
            ->once()
            ->with('ch_platform_held_test', ['expand' => ['transfer']])
            ->andReturn($charge);
        $gateway->shouldNotReceive('reverseTransfer');
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $stripeDispute = (object) ['charge' => 'ch_platform_held_test'];

        $this->disputeService->handleDisputeLoss($dispute, $stripeDispute);

        $dispute->refresh();
        $this->assertNull($dispute->stripe_reversal_id, 'No reversal was attempted, so no reversal id must be recorded.');
        $this->assertNull($dispute->reversed_amount);

        $timelineRow = DB::table('dispute_timeline')
            ->where('dispute_id', $dispute->id)
            ->where('event_type', 'system_action')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($timelineRow);
        $this->assertStringContainsString('not applicable', $timelineRow->event_data);
        $this->assertStringContainsString('platform-held', $timelineRow->event_data);
    }

    /** @test */
    public function handle_dispute_loss_reverses_transfer_for_a_destination_charge_as_before()
    {
        $dispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_destination_' . uniqid(),
            'status' => 'lost',
        ]);
        $this->trackDisputeCascade($dispute);

        $charge = (object) [
            'id' => 'ch_destination_test',
            'transfer' => (object) ['id' => 'tr_test123', 'amount' => 14660],
        ];

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('retrieveCharge')
            ->once()
            ->with('ch_destination_test', ['expand' => ['transfer']])
            ->andReturn($charge);
        $gateway->shouldReceive('reverseTransfer')
            ->once()
            ->with('tr_test123', Mockery::type('array'))
            ->andReturn((object) ['id' => 'trr_test123']);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $stripeDispute = (object) ['charge' => 'ch_destination_test'];

        $this->disputeService->handleDisputeLoss($dispute, $stripeDispute);

        $dispute->refresh();
        $this->assertEquals('trr_test123', $dispute->stripe_reversal_id);
        $this->assertEquals(14660, $dispute->reversed_amount);
    }
}
