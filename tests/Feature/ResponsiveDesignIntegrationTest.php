<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\TourBooking;
use App\Services\Dispute\DisputeService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * Integration tests for responsive design across device types
 * 
 * Tests Requirements:
 * - 13.1: Responsive design across device types
 * - 13.2: Mobile-optimized interface elements
 * - 13.3: Touch-friendly interactions
 * - 13.4: Cross-platform functionality
 */
class ResponsiveDesignIntegrationTest extends TestCase
{
    use WithFaker;

    protected $admin;
    protected $disputeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);
        
        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view-payment-dispute']);
        
        // Mock the DisputeService
        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->app->instance(DisputeService::class, $this->disputeService);

        $auditLogger = Mockery::mock(\App\Services\Dispute\DisputeAuditLogger::class);
        $auditLogger->shouldReceive('logListAccess')->andReturn(null);
        $auditLogger->shouldReceive('logDisputeAccess')->andReturn(null);
        $this->app->instance(\App\Services\Dispute\DisputeAuditLogger::class, $auditLogger);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');
    }

    /**
     * Test mobile viewport elements are present
     * Validates: Requirements 13.1, 13.2
     */
    public function test_mobile_viewport_elements_are_present()
    {
        $dm = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_mobile_test', 'status' => 'warning_needs_response', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent', 'evidence_due_by' => now()->addDays(5)]);
        $dm->setRelation('booking', (object) ['id' => 123, 'name' => 'John Doe']);
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$dm]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([
                'total_disputes' => 1,
                'active_disputes' => 1,
                'overdue_disputes' => 0,
                'requiring_attention' => 1
            ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Check for mobile-specific CSS classes
        $response->assertSee('sm:hidden'); // Mobile-only elements
        $response->assertSee('lg:hidden'); // Hide on desktop
        $response->assertSee('grid-cols-2'); // Mobile grid layout
        $response->assertSee('flex items-center justify-between'); // Toolbar responsive layout

        // Check for mobile search input
        $response->assertSee('sm:hidden space-y-3', false); // Mobile search section

        // Check for mobile filter toggle
        $response->assertSee('toggleMobileFilters');
        $response->assertSee('Advanced Filters');
    }

    /**
     * Test desktop viewport elements are present
     * Validates: Requirements 13.1
     */
    public function test_desktop_viewport_elements_are_present()
    {
        $dd = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_desktop_test', 'status' => 'under_review', 'amount' => 250.00, 'currency' => 'USD', 'reason' => 'product_unacceptable', 'evidence_due_by' => now()->addDays(3)]);
        $dd->setRelation('booking', (object) ['id' => 456, 'name' => 'Jane Smith']);
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$dd]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Check for desktop-specific elements
        $response->assertSee('hidden lg:block'); // Desktop table
        $response->assertSee('hidden sm:flex'); // Desktop filter layout
        $response->assertSee('table-auto'); // Desktop table styling

        // Check for desktop column headers
        $response->assertSee('Dispute ID');
        $response->assertSee('Booking');
        $response->assertSee('Amount');
        $response->assertSee('Reason');
        $response->assertSee('Status');
        $response->assertSee('Deadline');
        $response->assertSee('Actions');
    }

    /**
     * Test mobile card layout for dispute list
     * Validates: Requirements 13.1, 13.2
     */
    public function test_mobile_card_layout_for_dispute_list()
    {
        $dc = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_card_test', 'status' => 'warning_needs_response', 'amount' => 175.50, 'currency' => 'USD', 'reason' => 'duplicate', 'evidence_due_by' => now()->addDays(2)]);
        $dc->setRelation('booking', (object) ['id' => 789, 'name' => 'Bob Johnson']);
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$dc]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Check for mobile card layout
        $response->assertSee('lg:hidden divide-y divide-gray-50'); // Mobile card container
        $response->assertSee('p-4 hover:bg-gray-50/50 transition-colors'); // Card styling
        $response->assertSee('grid grid-cols-2 gap-x-4'); // Mobile grid layout
        $response->assertSee('flex gap-2 pt-3 border-t border-gray-100'); // Mobile action buttons

        // Check for mobile-optimized content
        $response->assertSee('text-xs text-gray-400'); // Mobile label styling
        $response->assertSee('text-sm font-medium'); // Mobile content styling
    }

    /**
     * Test responsive filter interface
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_filter_interface()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for responsive filter elements
        $response->assertSee('mobileFilters'); // Mobile filter section
        $response->assertSee('toggleMobileFilters'); // Mobile filter toggle
        $response->assertSee('mobileFilterIcon'); // Mobile filter icon

        // Check for desktop filter layout
        $response->assertSee('hidden sm:flex sm:flex-wrap'); // Desktop filter layout
        $response->assertSee('flex-1 min-w-[200px] max-w-[300px]'); // Responsive search input
        $response->assertSee('min-w-[140px]'); // Responsive select inputs

        // Check for mobile-specific filter elements
        $response->assertSee('flex gap-2'); // Mobile filter action buttons
        $response->assertSee('flex-1 px-4 py-2'); // Mobile filter buttons
    }

    /**
     * Test responsive statistics cards
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_statistics_cards()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);

        $statistics = [
            'total_disputes' => 25,
            'active_disputes' => 12,
            'overdue_disputes' => 4,
            'requiring_attention' => 8
        ];

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for responsive grid layout
        $response->assertSee('grid grid-cols-2 lg:grid-cols-4'); // Responsive grid
        $response->assertSee('gap-3'); // Responsive gap

        // Check for responsive card styling
        $response->assertSee('px-5 py-4'); // Card padding
        $response->assertSee('text-2xl font-bold'); // Stat number size
        $response->assertSee('rounded-2xl border border-gray-100'); // Card border radius

        // Verify statistics are displayed
        $response->assertSee('25'); // Total disputes
        $response->assertSee('12'); // Active disputes
        $response->assertSee('4');  // Overdue disputes
        $response->assertSee('8');  // Requiring attention
    }

    /**
     * Test responsive dispute detail view
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_dispute_detail_view()
    {
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_detail_responsive',
            'status' => 'warning_needs_response',
            'amount' => 300.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(4),
            'created_at' => now()->subDays(1),
        ]);
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        
        // Check for responsive layout
        $response->assertSee('grid grid-cols-1 lg:grid-cols-3'); // Responsive grid
        $response->assertSee('lg:col-span-2'); // Desktop column span
        $response->assertSee('space-y-6'); // Responsive spacing

        // Check for responsive cards
        $response->assertSee('p-5'); // Card padding
        $response->assertSee('rounded-2xl'); // Border radius
        $response->assertSee('grid grid-cols-1 sm:grid-cols-2'); // Responsive info grid

        // Check for responsive buttons
        $response->assertSee('flex items-center gap-3'); // Action button layout
    }

    /**
     * Test responsive evidence modal
     * Validates: Requirements 13.1, 13.2, 13.3
     */
    public function test_responsive_evidence_modal()
    {
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_modal_responsive',
            'status' => 'warning_needs_response',
            'amount' => 150.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'created_at' => now()->subDays(1),
        ]);
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        
        // Check for responsive modal
        $response->assertSee('w-full max-w-2xl'); // Responsive modal width
        $response->assertSee('evidenceModal'); // Modal ID

        // Check for responsive form elements
        $response->assertSee('space-y-2.5'); // Responsive form spacing (file rows container)
        $response->assertSee('w-full px-3 py-2'); // Full-width inputs

        // Check for responsive file upload area
        $response->assertSee('border-dashed border-gray-300'); // File upload border
        $response->assertSee('px-6 py-5'); // Upload area padding

        // Check for responsive buttons
        $response->assertSee('flex items-center justify-end gap-3'); // Button layout
        $response->assertSee('px-4 py-2'); // Button padding
    }

    /**
     * Test touch-friendly button sizes and spacing
     * Validates: Requirements 13.3
     */
    public function test_touch_friendly_button_sizes_and_spacing()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_touch_test', 'status' => 'warning_needs_response', 'amount' => 200.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for touch-friendly button classes
        $response->assertSee('flex-1 text-center px-3 py-2'); // Touch-friendly full-width buttons
        $response->assertSee('gap-2'); // Adequate spacing between elements
        $response->assertSee('gap-3'); // Larger spacing for mobile

        // Check for mobile action buttons
        $response->assertSee('flex-1 text-center'); // Full-width mobile buttons
        $response->assertSee('flex gap-2'); // Side-by-side mobile buttons
    }

    /**
     * Test responsive pagination
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_pagination()
    {
        // Create paginated results
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]),
            100, // Total items
            25,  // Per page
            2    // Current page
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?page=2');

        $response->assertStatus(200);
        
        // Check for responsive pagination layout
        $response->assertSee('flex flex-col sm:flex-row sm:items-center sm:justify-between'); // Responsive pagination container
        $response->assertSee('text-xs text-gray-400'); // Result count styling
        $response->assertSee('gap-3'); // Responsive spacing
    }

    /**
     * Test responsive sorting controls
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_sorting_controls()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for responsive sorting layout
        $response->assertSee('flex items-center justify-between'); // Toolbar container
        $response->assertSee('flex items-center gap-2'); // Sort controls layout
        $response->assertSee('px-2.5 py-1.5'); // Sort select padding
        $response->assertSee('border border-gray-200 rounded-lg'); // Sort direction button border

        // Check for responsive result count display
        $response->assertSee('text-xs text-gray-400'); // Result count styling
    }

    /**
     * Test responsive empty state
     * Validates: Requirements 13.1, 13.2
     */
    public function test_responsive_empty_state()
    {
        $emptyDisputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($emptyDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for responsive empty state
        $response->assertSee('py-16 text-center'); // Centered empty state
        $response->assertSee('flex flex-col items-center gap-3'); // Centered icon layout
        $response->assertSee('text-sm font-semibold text-gray-500'); // Empty state heading
        $response->assertSee('text-xs text-gray-400'); // Empty state description

        // Verify empty state content
        $response->assertSee('No disputes found');
        $response->assertSee('Try adjusting your filters');
    }

    /**
     * Test responsive JavaScript functionality
     * Validates: Requirements 13.1, 13.3
     */
    public function test_responsive_javascript_functionality()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        
        // Check for responsive JavaScript elements
        $response->assertSee('toggleMobileFilters'); // Mobile filter toggle
        $response->assertSee('mobileFilterIcon'); // Mobile filter icon
        $response->assertSee('handleResponsiveTable'); // Responsive table handler
        $response->assertSee("$(window).on('resize'", false); // Window resize handler (unescaped JS)

        // Check for event handling
        $response->assertSee("on('click'", false); // Click event handlers (unescaped JS)
        $response->assertSee('slideUp'); // Mobile animations
        $response->assertSee('slideDown'); // Mobile animations
    }

    protected function tearDown(): void
    {
        Mockery::close();

        if (isset($this->admin)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
        }

        parent::tearDown();
    }
}