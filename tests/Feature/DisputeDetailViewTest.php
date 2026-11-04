<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Models\Admin;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DisputeDetailViewTest extends TestCase
{
    use WithFaker;

    protected $admin;
    protected $dispute;

    /**
     * tests/TestCase.php runs against the real tenant DB with no transaction/rollback
     * wrapper, so every Admin::factory()/TourBooking::factory()/Transaction::factory()/
     * Dispute::factory() row created here is a real, permanent write unless torn down
     * explicitly. Track every id created by this test class so tearDown() can remove
     * the full dependent chain after each test method.
     */
    protected array $createdAdminIds = [];
    protected array $createdBookingIds = [];
    protected array $createdTransactionIds = [];
    protected array $createdDisputeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);

        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->createdAdminIds[] = $this->admin->id;
        $this->admin->givePermissionTo(['view-payment-dispute']);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');

        // Create test dispute with related data
        $booking = TourBooking::factory()->create();
        $this->createdBookingIds[] = $booking->id;
        $transaction = Transaction::factory()->create([
            'tour_booking_id' => $booking->id
        ]);
        $this->createdTransactionIds[] = $transaction->id;

        $this->dispute = Dispute::factory()->create([
            'booking_id' => $booking->id,
            'transaction_id' => $transaction->id,
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(5)
        ]);
        $this->createdDisputeIds[] = $this->dispute->id;
    }

    protected function tearDown(): void
    {
        // dispute_audit_trail.dispute_id has no FK constraint (plain indexed column),
        // so rows written by the controller (e.g. the "dispute_viewed" entry logged on
        // every successful admin.disputes.show request) won't be removed by the
        // disputes cascade below — delete them explicitly first.
        if (!empty($this->createdDisputeIds)) {
            DB::table('dispute_audit_trail')->whereIn('dispute_id', $this->createdDisputeIds)->delete();
        }

        if (!empty($this->createdDisputeIds) || !empty($this->createdBookingIds)) {
            DB::table('disputes')
                ->where(function ($query) {
                    if (!empty($this->createdDisputeIds)) {
                        $query->orWhereIn('id', $this->createdDisputeIds);
                    }
                    if (!empty($this->createdBookingIds)) {
                        $query->orWhereIn('booking_id', $this->createdBookingIds);
                    }
                })
                ->delete();
        }

        if (!empty($this->createdTransactionIds) || !empty($this->createdBookingIds)) {
            DB::table('transactions')
                ->where(function ($query) {
                    if (!empty($this->createdTransactionIds)) {
                        $query->orWhereIn('id', $this->createdTransactionIds);
                    }
                    if (!empty($this->createdBookingIds)) {
                        $query->orWhereIn('tour_booking_id', $this->createdBookingIds);
                    }
                })
                ->delete();
        }

        if (!empty($this->createdBookingIds)) {
            DB::table('tour_bookings_transact')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_booking_history')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_master')->whereIn('id', $this->createdBookingIds)->delete();
        }

        if (!empty($this->createdAdminIds)) {
            DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * A bare Dispute::factory()->create() with no explicit transaction_id override
     * cascades a Transaction row via TransactionFactory's own default
     * 'tour_booking_id' => TourBooking::factory() — a SEPARATE booking from the
     * dispute's own booking_id, since Laravel resolves each nested factory
     * independently. Track the dispute, its own booking_id/transaction_id, AND
     * that separately-cascaded booking.
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
    public function it_displays_dispute_detail_view_for_authorized_user()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.disputes.show');
        $response->assertViewHas('dispute');
        $response->assertSee($this->dispute->stripe_dispute_id);
        $response->assertSee($this->dispute->status_label);
        $response->assertSee('$' . number_format($this->dispute->amount, 2));
    }

    /** @test */
    public function it_shows_dispute_information_correctly()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee($this->dispute->stripe_dispute_id);
        $response->assertSee($this->dispute->reason_label);
        $response->assertSee($this->dispute->status_label);
        $response->assertSee('$' . number_format($this->dispute->amount, 2));
        
        if ($this->dispute->evidence_due_by) {
            $response->assertSee($this->dispute->evidence_due_by->format('M j, Y'));
        }
    }

    /** @test */
    public function it_shows_associated_booking_information()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('Associated Booking');
        $response->assertSee('#' . $this->dispute->booking->id);
        
        if ($this->dispute->booking->name) {
            $response->assertSee($this->dispute->booking->name);
        }
    }

    /** @test */
    public function it_displays_evidence_files_section()
    {
        // "Submitted to Stripe" renders only when isSubmittedToStripe() is
        // true, which needs BOTH stripe_file_id AND submitted_at to be non-null.
        // The factory's default stripe_file_id is optional(0.7), so relying on
        // it fails ~30% of runs. Use the factory's submitted()/pending() states
        // to lock both fields deterministically.
        $evidence1 = DisputeEvidence::factory()->submitted()->create([
            'dispute_id' => $this->dispute->id,
            'evidence_type' => 'receipt',
            'file_name' => 'receipt.pdf',
        ]);

        $evidence2 = DisputeEvidence::factory()->pending()->create([
            'dispute_id' => $this->dispute->id,
            'evidence_type' => 'customer_communication',
            'file_name' => 'email.pdf',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('Evidence Files');
        $response->assertSee($evidence1->file_name);
        $response->assertSee($evidence2->file_name);
        $response->assertSee('Submitted to Stripe');
        $response->assertSee('Pending submission');
    }

    /** @test */
    public function it_displays_timeline_events()
    {
        // Create timeline events
        $event1 = DisputeTimeline::factory()->create([
            'dispute_id' => $this->dispute->id,
            'event_type' => 'dispute_created',
            'event_data' => ['source' => 'stripe_webhook']
        ]);

        $event2 = DisputeTimeline::factory()->create([
            'dispute_id' => $this->dispute->id,
            'event_type' => 'evidence_submitted',
            'event_data' => ['evidence_count' => 2]
        ]);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('Activity Timeline');
        $response->assertSee($event1->event_label);
        $response->assertSee($event2->event_label);
    }

    /** @test */
    public function it_shows_urgent_alert_for_disputes_requiring_attention()
    {
        // Create dispute with approaching deadline
        $urgentDispute = Dispute::factory()->create([
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(2) // 2 days left - should be urgent
        ]);
        $this->trackDisputeCascade($urgentDispute);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $urgentDispute->id));

        $response->assertSee('Deadline Approaching');
        $response->assertSee('Evidence must be submitted within 2 day(s)');
    }

    /** @test */
    public function it_shows_overdue_alert_for_overdue_disputes()
    {
        // Create overdue dispute
        $overdueDispute = Dispute::factory()->create([
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->subDays(1) // 1 day overdue
        ]);
        $this->trackDisputeCascade($overdueDispute);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $overdueDispute->id));

        $response->assertSee('Overdue: Evidence Required Immediately');
        $response->assertSee('This dispute is overdue by 1 day(s)');
    }

    /** @test */
    public function it_shows_submit_evidence_button_for_active_disputes()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('Submit Evidence');
        $response->assertSee('Upload Evidence');
    }

    /** @test */
    public function it_hides_submit_evidence_button_for_closed_disputes()
    {
        $closedDispute = Dispute::factory()->create([
            'status' => 'won'
        ]);
        $this->trackDisputeCascade($closedDispute);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $closedDispute->id));

        $response->assertDontSee('Submit Evidence');
        $response->assertDontSee('Upload Evidence');
    }

    /** @test */
    public function it_shows_quick_actions_section()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        if ($this->dispute->booking) {
            $response->assertSee('View Booking');
        }
    }

    /** @test */
    public function it_redirects_to_index_when_dispute_not_found()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', 99999));

        $response->assertRedirect(route('admin.disputes.index'));
        $response->assertSessionHas('error', 'Dispute not found.');
    }

    /** @test */
    public function unauthorized_user_cannot_access_dispute_details()
    {
        $unauthorizedAdmin = Admin::factory()->create();
        $this->createdAdminIds[] = $unauthorizedAdmin->id;

        $response = $this->actingAs($unauthorizedAdmin, 'admin')
                         ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_includes_evidence_submission_modal()
    {
        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('evidenceModal');
        $response->assertSee('Evidence Documents');
        $response->assertSee('Evidence Text');
    }

    /** @test */
    public function it_includes_status_update_modal_for_admin_users()
    {
        // Give admin user the view-payment-dispute permission
        $this->admin->givePermissionTo('view-payment-dispute');

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $this->dispute->id));

        $response->assertSee('statusModal');
        $response->assertSee('Update Status');
        $response->assertSee('New Status');
    }

    /** @test */
    public function it_calculates_days_until_deadline_correctly()
    {
        $disputeWithDeadline = Dispute::factory()->create([
            'evidence_due_by' => now()->addDays(7)
        ]);
        $this->trackDisputeCascade($disputeWithDeadline);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $disputeWithDeadline->id));

        $response->assertSee('7 day(s) remaining');
    }

    /** @test */
    public function it_handles_disputes_without_deadlines()
    {
        $disputeWithoutDeadline = Dispute::factory()->create([
            'evidence_due_by' => null
        ]);
        $this->trackDisputeCascade($disputeWithoutDeadline);

        $response = $this->actingAs($this->admin, 'admin')
             ->withSession(['userType' => 'Admin'])
             ->get(route('admin.disputes.show', $disputeWithoutDeadline->id));

        $response->assertSee('No deadline set');
    }
}