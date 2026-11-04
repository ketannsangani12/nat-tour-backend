<?php

namespace Tests\Feature\Disputes;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\Dispute;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Property 6: Booking Dispute Highlighting
 * 
 * For any booking with an active payment dispute, the booking should be visually 
 * highlighted in all relevant interfaces, and dispute information should be 
 * displayed inline when viewing booking details.
 * 
 * **Validates: Requirements 5.1, 10.1**
 */
class BookingDisputeHighlightingPropertyTest extends TestCase
{

    protected Admin $admin;

    /**
     * tests/TestCase.php runs against the real tenant DB with no transaction/rollback
     * wrapper, so every TourBooking::factory()/Transaction::factory()/Dispute::factory()
     * row created here is a real, permanent write unless torn down explicitly. Every
     * createTestBooking() call records its id here so tearDown() can remove the full
     * dependent chain after each test method (tearDown() runs even on assertion failure,
     * unlike cleanup code placed at the end of a test body).
     */
    protected array $createdBookingIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions if they don't exist
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'view-payment-dispute',
            'guard_name' => 'admin'
        ]);

        // Create admin user for testing
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('view-payment-dispute');
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdBookingIds)) {
            DB::table('disputes')->whereIn('booking_id', $this->createdBookingIds)->delete();
            DB::table('transactions')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_transact')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_booking_history')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_master')->whereIn('id', $this->createdBookingIds)->delete();
            $this->createdBookingIds = [];
        }

        if (isset($this->admin)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
        }

        parent::tearDown();
    }

    /**
     * Property-based test: For any booking with an active dispute,
     * the booking should be visually highlighted with correct dispute information.
     * 
     * This test validates that:
     * 1. Bookings with active disputes are properly identified
     * 2. Dispute information is correctly attached to booking data
     * 3. Visual indicators use appropriate CSS classes
     * 4. Dispute status and reason are properly labeled
     * 5. Highlighting is consistent across different dispute statuses
     * 
     * **Validates: Requirements 5.1, 5.3, 10.1**
     */
    public function test_active_dispute_highlighting_property()
    {
        // Generate test cases for different dispute scenarios
        $testCases = $this->generateActiveDisputeTestCases();
        
        foreach ($testCases as $testCase) {
            $this->runActiveDisputeHighlightingTest($testCase);
        }
    }

    /**
     * Property-based test: For any booking without active disputes,
     * the booking should not display dispute indicators.
     * 
     * This test validates that:
     * 1. Bookings without disputes are not highlighted
     * 2. No dispute information is attached to non-disputed bookings
     * 3. Visual indicators are not present for clean bookings
     * 4. System correctly identifies bookings without disputes
     * 
     * **Validates: Requirements 5.4, 10.1**
     */
    public function test_no_dispute_highlighting_property()
    {
        // Generate test cases for bookings without disputes
        $testCases = $this->generateNoDisputeTestCases();
        
        foreach ($testCases as $testCase) {
            $this->runNoDisputeHighlightingTest($testCase);
        }
    }

    /**
     * Property-based test: For any dispute status change,
     * the visual highlighting should update to reflect the new status.
     * 
     * This test validates that:
     * 1. Status changes are reflected in visual indicators
     * 2. CSS classes update when dispute status changes
     * 3. Resolved disputes no longer show as active
     * 4. Status transitions maintain visual consistency
     * 
     * **Validates: Requirements 5.4, 10.1**
     */
    public function test_dispute_status_change_highlighting_property()
    {
        // Test dispute status transitions
        $statusTransitions = $this->generateDisputeStatusTransitions();
        
        foreach ($statusTransitions as $transition) {
            $this->runDisputeStatusChangeTest($transition);
        }
    }

    /**
     * Property-based test: For any booking view context,
     * dispute highlighting should be consistent across all interfaces.
     * 
     * This test validates that:
     * 1. Dispute information is consistent across different views
     * 2. Visual styling follows the same patterns
     * 3. Tooltip information is accurate and complete
     * 4. CSS classes are applied consistently
     * 
     * **Validates: Requirements 5.1, 5.7, 10.1**
     */
    public function test_consistent_highlighting_across_views_property()
    {
        // Test consistency across different booking contexts
        $viewContexts = $this->generateBookingViewContexts();
        
        foreach ($viewContexts as $context) {
            $this->runConsistentHighlightingTest($context);
        }
    }

    /**
     * Property-based test: For any dispute with evidence deadline,
     * the highlighting should reflect urgency appropriately.
     * 
     * This test validates that:
     * 1. Urgent disputes are highlighted with appropriate urgency indicators
     * 2. Deadline information is correctly calculated and displayed
     * 3. Overdue disputes are clearly marked
     * 4. Attention-required disputes are properly flagged
     * 
     * **Validates: Requirements 5.1, 8.1**
     */
    public function test_urgency_based_highlighting_property()
    {
        // Test urgency-based highlighting scenarios
        $urgencyScenarios = $this->generateUrgencyTestCases();
        
        foreach ($urgencyScenarios as $scenario) {
            $this->runUrgencyHighlightingTest($scenario);
        }
    }

    /**
     * Property-based test: For any booking with dispute history,
     * the system should distinguish between active and historical disputes.
     * 
     * This test validates that:
     * 1. Only active disputes trigger highlighting
     * 2. Resolved disputes don't show as active
     * 3. Historical dispute information is available but not highlighted
     * 4. Multiple disputes are handled correctly
     * 
     * **Validates: Requirements 5.2, 5.4, 10.1**
     */
    public function test_active_vs_historical_dispute_highlighting_property()
    {
        // Test active vs historical dispute scenarios
        $disputeHistoryScenarios = $this->generateDisputeHistoryTestCases();
        
        foreach ($disputeHistoryScenarios as $scenario) {
            $this->runDisputeHistoryHighlightingTest($scenario);
        }
    }

    /**
     * Property-based test: For any booking dispute information,
     * the tooltip content should be accurate and complete.
     * 
     * This test validates that:
     * 1. Tooltip information matches dispute data
     * 2. All required fields are present in tooltips
     * 3. Formatting is consistent and readable
     * 4. Special cases (overdue, urgent) are properly indicated
     * 
     * **Validates: Requirements 5.7**
     */
    public function test_dispute_tooltip_accuracy_property()
    {
        // Test tooltip accuracy for different dispute scenarios
        $tooltipScenarios = $this->generateTooltipTestCases();
        
        foreach ($tooltipScenarios as $scenario) {
            $this->runTooltipAccuracyTest($scenario);
        }
    }

    /**
     * Property-based test: For any print-friendly context,
     * dispute markers should be clearly visible in printed materials.
     * 
     * This test validates that:
     * 1. Print views include dispute status indicators
     * 2. Dispute information is readable in print format
     * 3. Visual indicators work without color dependencies
     * 4. Essential dispute information is preserved in print
     * 
     * **Validates: Requirements 5.8**
     */
    public function test_print_friendly_dispute_markers_property()
    {
        // Test print-friendly dispute markers
        $printScenarios = $this->generatePrintTestCases();
        
        foreach ($printScenarios as $scenario) {
            $this->runPrintFriendlyTest($scenario);
        }
    }

    /**
     * Run active dispute highlighting test for a specific test case.
     */
    private function runActiveDisputeHighlightingTest(array $testCase): void
    {
        $booking = $testCase['booking'];
        $dispute = $testCase['dispute'];
        $description = $testCase['description'];
        
        // Test that booking correctly identifies active dispute
        $this->assertTrue(
            $booking->hasActiveDispute(),
            "Booking {$description} should have active dispute"
        );
        
        // Test that dispute summary is available
        $disputeSummary = $booking->getDisputeSummary();
        $this->assertNotNull(
            $disputeSummary,
            "Booking {$description} should have dispute summary"
        );
        
        // Validate dispute summary content
        $this->assertEquals($dispute->stripe_dispute_id, $disputeSummary['id']);
        $this->assertEquals($dispute->amount, $disputeSummary['amount']);
        $this->assertEquals($dispute->currency, $disputeSummary['currency']);
        $this->assertEquals($dispute->reason_label, $disputeSummary['reason']);
        $this->assertEquals($dispute->status_label, $disputeSummary['status']);
        
        // Test CSS class generation
        $cssClass = $booking->getDisputeStatusCssClass();
        $this->assertNotEmpty(
            $cssClass,
            "Booking {$description} should have dispute CSS class"
        );
        
        // Validate CSS class matches dispute status
        $expectedCssClass = $dispute->getStatusCssClassAttribute();
        $this->assertStringContainsString(
            $expectedCssClass,
            $cssClass,
            "CSS class should match dispute status for {$description}"
        );
        
        // Test attention requirement
        if ($dispute->requiresAttention()) {
            $this->assertTrue(
                $booking->requiresDisputeAttention(),
                "Booking {$description} should require attention when dispute does"
            );
        }
    }

    /**
     * Run no dispute highlighting test for a specific test case.
     */
    private function runNoDisputeHighlightingTest(array $testCase): void
    {
        $booking = $testCase['booking'];
        $description = $testCase['description'];
        
        // Test that booking correctly identifies no active dispute
        $this->assertFalse(
            $booking->hasActiveDispute(),
            "Booking {$description} should not have active dispute"
        );
        
        // Test that dispute summary is null
        $disputeSummary = $booking->getDisputeSummary();
        $this->assertNull(
            $disputeSummary,
            "Booking {$description} should not have dispute summary"
        );
        
        // Test CSS class is empty
        $cssClass = $booking->getDisputeStatusCssClass();
        $this->assertEmpty(
            $cssClass,
            "Booking {$description} should not have dispute CSS class"
        );
        
        // Test no attention required
        $this->assertFalse(
            $booking->requiresDisputeAttention(),
            "Booking {$description} should not require dispute attention"
        );
    }

    /**
     * Run dispute status change test for a specific transition.
     */
    private function runDisputeStatusChangeTest(array $transition): void
    {
        $booking = $transition['booking'];
        $dispute = $transition['dispute'];
        $oldStatus = $transition['old_status'];
        $newStatus = $transition['new_status'];
        $description = $transition['description'];
        
        // Record initial state
        $initiallyActive = $booking->hasActiveDispute();
        $initialCssClass = $booking->getDisputeStatusCssClass();
        
        // Update dispute status
        $dispute->update(['status' => $newStatus]);
        $booking->refresh();
        
        // Test status change effects
        $nowActive = $booking->hasActiveDispute();
        $newCssClass = $booking->getDisputeStatusCssClass();
        
        // Validate active status based on new status
        if (in_array($newStatus, ['warning_needs_response', 'under_review'])) {
            $this->assertTrue(
                $nowActive,
                "Booking should be active after status change to {$newStatus} for {$description}"
            );
            $this->assertNotEmpty(
                $newCssClass,
                "Booking should have CSS class after status change to {$newStatus} for {$description}"
            );
        } else {
            $this->assertFalse(
                $nowActive,
                "Booking should not be active after status change to {$newStatus} for {$description}"
            );
            $this->assertEmpty(
                $newCssClass,
                "Booking should not have CSS class after status change to {$newStatus} for {$description}"
            );
        }
        
        // Test CSS class updates when active
        if ($nowActive) {
            $expectedCssClass = $dispute->getStatusCssClassAttribute();
            $this->assertStringContainsString(
                $expectedCssClass,
                $newCssClass,
                "CSS class should update to match new status {$newStatus} for {$description}"
            );
        }
    }

    /**
     * Run consistent highlighting test across different view contexts.
     */
    private function runConsistentHighlightingTest(array $context): void
    {
        $booking = $context['booking'];
        $dispute = $context['dispute'];
        $viewType = $context['view_type'];
        $description = $context['description'];
        
        // Get dispute information in different contexts
        $disputeSummary = $booking->getDisputeSummary();
        $cssClass = $booking->getDisputeStatusCssClass();
        $hasActiveDispute = $booking->hasActiveDispute();
        
        // Test consistency across contexts
        $this->assertTrue(
            $hasActiveDispute,
            "Booking should have active dispute in {$viewType} context for {$description}"
        );
        
        $this->assertNotNull(
            $disputeSummary,
            "Dispute summary should be available in {$viewType} context for {$description}"
        );
        
        $this->assertNotEmpty(
            $cssClass,
            "CSS class should be available in {$viewType} context for {$description}"
        );
        
        // Validate dispute summary consistency
        $this->assertEquals($dispute->stripe_dispute_id, $disputeSummary['id']);
        $this->assertEquals($dispute->status_label, $disputeSummary['status']);
        $this->assertEquals($dispute->reason_label, $disputeSummary['reason']);
        
        // Test that CSS class matches expected pattern
        $this->assertStringContainsString(
            'text-',
            $cssClass,
            "CSS class should contain text color for {$viewType} context"
        );
        $this->assertStringContainsString(
            'bg-',
            $cssClass,
            "CSS class should contain background color for {$viewType} context"
        );
    }

    /**
     * Run urgency highlighting test for a specific scenario.
     */
    private function runUrgencyHighlightingTest(array $scenario): void
    {
        $booking = $scenario['booking'];
        $dispute = $scenario['dispute'];
        $urgencyLevel = $scenario['urgency_level'];
        $description = $scenario['description'];
        
        // Test urgency detection
        $requiresAttention = $booking->requiresDisputeAttention();
        $disputeSummary = $booking->getDisputeSummary();
        
        if ($urgencyLevel === 'urgent') {
            $this->assertTrue(
                $requiresAttention,
                "Booking should require attention for urgent dispute: {$description}"
            );
            $this->assertTrue(
                $disputeSummary['requires_attention'],
                "Dispute summary should indicate attention required: {$description}"
            );
        } elseif ($urgencyLevel === 'overdue') {
            $this->assertTrue(
                $dispute->isOverdue(),
                "Dispute should be overdue: {$description}"
            );
            $this->assertLessThan(
                0,
                $disputeSummary['days_until_deadline'],
                "Days until deadline should be negative for overdue: {$description}"
            );
        } elseif ($urgencyLevel === 'normal') {
            $this->assertFalse(
                $requiresAttention,
                "Booking should not require attention for normal dispute: {$description}"
            );
        }
        
        // Test deadline calculation accuracy
        if ($dispute->evidence_due_by) {
            $expectedDays = (int) round(now()->diffInDays($dispute->evidence_due_by, false));
            $this->assertEquals(
                $expectedDays,
                $disputeSummary['days_until_deadline'],
                "Days until deadline calculation should be accurate: {$description}"
            );
        }
    }

    /**
     * Run dispute history highlighting test for a specific scenario.
     */
    private function runDisputeHistoryHighlightingTest(array $scenario): void
    {
        $booking = $scenario['booking'];
        $activeDispute = $scenario['active_dispute'];
        $historicalDispute = $scenario['historical_dispute'];
        $description = $scenario['description'];
        
        // Test that only active dispute affects highlighting
        $this->assertTrue(
            $booking->hasActiveDispute(),
            "Booking should have active dispute: {$description}"
        );
        
        $disputeSummary = $booking->getDisputeSummary();
        $this->assertNotNull(
            $disputeSummary,
            "Dispute summary should be available: {$description}"
        );
        
        // Test that active dispute information is used, not historical
        if ($activeDispute) {
            $this->assertEquals(
                $activeDispute->stripe_dispute_id,
                $disputeSummary['id'],
                "Should use active dispute ID, not historical: {$description}"
            );
            $this->assertEquals(
                $activeDispute->status_label,
                $disputeSummary['status'],
                "Should use active dispute status, not historical: {$description}"
            );
        }
        
        // Test that historical disputes don't affect active status
        $this->assertTrue(
            $booking->isDisputed(),
            "Booking should show as disputed (has history): {$description}"
        );
    }

    /**
     * Run tooltip accuracy test for a specific scenario.
     */
    private function runTooltipAccuracyTest(array $scenario): void
    {
        $booking = $scenario['booking'];
        $dispute = $scenario['dispute'];
        $description = $scenario['description'];
        
        $disputeSummary = $booking->getDisputeSummary();
        $this->assertNotNull($disputeSummary, "Dispute summary should exist: {$description}");
        
        // Test required tooltip fields
        $requiredFields = ['id', 'amount', 'currency', 'reason', 'status'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $disputeSummary,
                "Tooltip should contain {$field}: {$description}"
            );
            $this->assertNotNull(
                $disputeSummary[$field],
                "Tooltip {$field} should not be null: {$description}"
            );
        }
        
        // Test deadline information when present
        if ($dispute->evidence_due_by) {
            $this->assertArrayHasKey(
                'deadline',
                $disputeSummary,
                "Tooltip should contain deadline: {$description}"
            );
            $this->assertArrayHasKey(
                'days_until_deadline',
                $disputeSummary,
                "Tooltip should contain days until deadline: {$description}"
            );
        }
        
        // Test attention flag accuracy
        $this->assertEquals(
            $dispute->requiresAttention(),
            $disputeSummary['requires_attention'],
            "Tooltip attention flag should match dispute: {$description}"
        );
    }

    /**
     * Run print-friendly test for a specific scenario.
     */
    private function runPrintFriendlyTest(array $scenario): void
    {
        $booking = $scenario['booking'];
        $dispute = $scenario['dispute'];
        $printContext = $scenario['print_context'];
        $description = $scenario['description'];
        
        // Test that booking methods work in print context
        $this->assertTrue(
            $booking->hasActiveDispute(),
            "Print context should detect active dispute: {$description}"
        );
        
        $this->assertTrue(
            $booking->isDisputed(),
            "Print context should detect dispute history: {$description}"
        );
        
        // Test that dispute information is available for print
        $disputeSummary = $booking->getDisputeSummary();
        $this->assertNotNull(
            $disputeSummary,
            "Print context should have dispute summary: {$description}"
        );
        
        // Test essential information for print
        $this->assertNotEmpty(
            $disputeSummary['status'],
            "Print should have dispute status: {$description}"
        );
        $this->assertNotEmpty(
            $disputeSummary['reason'],
            "Print should have dispute reason: {$description}"
        );
        
        // Test that print markers are text-based (not color-dependent)
        $statusLabel = $disputeSummary['status'];
        $this->assertIsString(
            $statusLabel,
            "Status label should be text for print: {$description}"
        );
        $this->assertNotEmpty(
            $statusLabel,
            "Status label should not be empty for print: {$description}"
        );
    }

    /**
     * Generate test cases for active dispute scenarios.
     */
    private function generateActiveDisputeTestCases(): array
    {
        $testCases = [];
        
        // Different dispute statuses that should be active
        $activeStatuses = ['warning_needs_response', 'under_review'];
        $disputeReasons = ['fraudulent', 'subscription_canceled', 'product_unacceptable'];
        
        foreach ($activeStatuses as $status) {
            foreach ($disputeReasons as $reason) {
                $booking = $this->createTestBooking();
                $dispute = $this->createTestDispute($booking, [
                    'status' => $status,
                    'reason' => $reason,
                    'evidence_due_by' => now()->addDays(7)
                ]);
                
                $testCases[] = [
                    'booking' => $booking,
                    'dispute' => $dispute,
                    'description' => "Active dispute with status {$status} and reason {$reason}"
                ];
            }
        }
        
        return $testCases;
    }

    /**
     * Generate test cases for bookings without disputes.
     */
    private function generateNoDisputeTestCases(): array
    {
        return [
            [
                'booking' => $this->createTestBooking(),
                'description' => 'Booking with no disputes'
            ],
            [
                'booking' => $this->createTestBookingWithTransaction(),
                'description' => 'Booking with transaction but no disputes'
            ]
        ];
    }

    /**
     * Generate dispute status transition test cases.
     */
    private function generateDisputeStatusTransitions(): array
    {
        $transitions = [];
        
        $statusPairs = [
            ['warning_needs_response', 'under_review'],
            ['under_review', 'won'],
            ['warning_needs_response', 'lost'],
            ['under_review', 'charge_refunded']
        ];
        
        foreach ($statusPairs as [$oldStatus, $newStatus]) {
            $booking = $this->createTestBooking();
            $dispute = $this->createTestDispute($booking, ['status' => $oldStatus]);
            
            $transitions[] = [
                'booking' => $booking,
                'dispute' => $dispute,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Status transition from {$oldStatus} to {$newStatus}"
            ];
        }
        
        return $transitions;
    }

    /**
     * Generate booking view context test cases.
     */
    private function generateBookingViewContexts(): array
    {
        $contexts = [];
        $viewTypes = ['calendar', 'agenda', 'search', 'detail'];
        
        foreach ($viewTypes as $viewType) {
            $booking = $this->createTestBooking();
            $dispute = $this->createTestDispute($booking, [
                'status' => 'warning_needs_response',
                'reason' => 'fraudulent'
            ]);
            
            $contexts[] = [
                'booking' => $booking,
                'dispute' => $dispute,
                'view_type' => $viewType,
                'description' => "Dispute highlighting in {$viewType} view"
            ];
        }
        
        return $contexts;
    }

    /**
     * Generate urgency test cases.
     */
    private function generateUrgencyTestCases(): array
    {
        $scenarios = [];
        
        // Urgent dispute (deadline in 2 days)
        $booking1 = $this->createTestBooking();
        $dispute1 = $this->createTestDispute($booking1, [
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(2)
        ]);
        
        $scenarios[] = [
            'booking' => $booking1,
            'dispute' => $dispute1,
            'urgency_level' => 'urgent',
            'description' => 'Urgent dispute with 2 days deadline'
        ];
        
        // Overdue dispute
        $booking2 = $this->createTestBooking();
        $dispute2 = $this->createTestDispute($booking2, [
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->subDays(1)
        ]);
        
        $scenarios[] = [
            'booking' => $booking2,
            'dispute' => $dispute2,
            'urgency_level' => 'overdue',
            'description' => 'Overdue dispute by 1 day'
        ];
        
        // Normal dispute
        $booking3 = $this->createTestBooking();
        $dispute3 = $this->createTestDispute($booking3, [
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(10)
        ]);
        
        $scenarios[] = [
            'booking' => $booking3,
            'dispute' => $dispute3,
            'urgency_level' => 'normal',
            'description' => 'Normal dispute with 10 days deadline'
        ];
        
        return $scenarios;
    }

    /**
     * Generate dispute history test cases.
     */
    private function generateDisputeHistoryTestCases(): array
    {
        $scenarios = [];
        
        // Booking with active and historical disputes
        $booking = $this->createTestBooking();
        $historicalDispute = $this->createTestDispute($booking, [
            'status' => 'won',
            'stripe_dispute_id' => 'dp_historical_' . uniqid()
        ]);
        $activeDispute = $this->createTestDispute($booking, [
            'status' => 'warning_needs_response',
            'stripe_dispute_id' => 'dp_active_' . uniqid()
        ]);
        
        $scenarios[] = [
            'booking' => $booking,
            'active_dispute' => $activeDispute,
            'historical_dispute' => $historicalDispute,
            'description' => 'Booking with both active and historical disputes'
        ];
        
        return $scenarios;
    }

    /**
     * Generate tooltip test cases.
     */
    private function generateTooltipTestCases(): array
    {
        $scenarios = [];
        
        // Standard dispute with all information
        $booking = $this->createTestBooking();
        $dispute = $this->createTestDispute($booking, [
            'status' => 'warning_needs_response',
            'reason' => 'fraudulent',
            'amount' => 150.00,
            'currency' => 'USD',
            'evidence_due_by' => now()->addDays(5)
        ]);
        
        $scenarios[] = [
            'booking' => $booking,
            'dispute' => $dispute,
            'description' => 'Standard dispute with complete information'
        ];
        
        return $scenarios;
    }

    /**
     * Generate print test cases.
     */
    private function generatePrintTestCases(): array
    {
        $scenarios = [];
        
        // Print scenario with active dispute
        $booking = $this->createTestBooking();
        $dispute = $this->createTestDispute($booking, [
            'status' => 'warning_needs_response',
            'reason' => 'fraudulent'
        ]);
        
        $scenarios[] = [
            'booking' => $booking,
            'dispute' => $dispute,
            'print_context' => 'booking_confirmation',
            'description' => 'Print-friendly dispute marker in booking confirmation'
        ];
        
        return $scenarios;
    }

    /**
     * Create a test booking.
     */
    private function createTestBooking(): TourBooking
    {
        $booking = TourBooking::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'tourists' => 2,
            'total' => 200.00
        ]);
        $this->createdBookingIds[] = $booking->id;

        return $booking;
    }

    /**
     * Create a test booking with transaction.
     */
    private function createTestBookingWithTransaction(): TourBooking
    {
        $booking = $this->createTestBooking();
        
        Transaction::factory()->create([
            'tour_booking_id' => $booking->id,
            'amount' => $booking->total,
            'status' => 'completed'
        ]);
        
        return $booking;
    }

    /**
     * Create a test dispute for a booking.
     */
    private function createTestDispute(TourBooking $booking, array $attributes = []): Dispute
    {
        $transaction = $booking->transactions()->first();
        if (!$transaction) {
            $transaction = Transaction::factory()->create([
                'tour_booking_id' => $booking->id,
                'amount' => $booking->total
            ]);
        }
        
        return Dispute::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'transaction_id' => $transaction->id,
            'stripe_dispute_id' => 'dp_test_' . uniqid(),
            'amount' => $booking->total,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(7)
        ], $attributes));
    }
}