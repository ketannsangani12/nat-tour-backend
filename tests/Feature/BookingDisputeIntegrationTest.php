<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TourBooking;
use App\Models\Dispute;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingDisputeIntegrationTest extends TestCase
{
    use WithFaker;

    protected $tenancy = true;

    /**
     * tests/TestCase.php runs against the real tenant DB with no transaction/rollback
     * wrapper, so every TourBooking::factory()/Dispute::factory() row created here
     * (and the Transaction row Dispute::factory() cascades into when transaction_id
     * isn't overridden) is a real, permanent write unless torn down explicitly.
     * Track every id created by this test class so tearDown() can remove the full
     * dependent chain after each test method.
     */
    protected array $createdBookingIds = [];
    protected array $createdTransactionIds = [];
    protected array $createdDisputeIds = [];
    protected array $createdCustomerIds = [];

    protected function tearDown(): void
    {
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

        if (!empty($this->createdCustomerIds)) {
            DB::table('customers')->whereIn('id', $this->createdCustomerIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Even when a Dispute::factory()->create() call explicitly overrides booking_id,
     * leaving transaction_id un-overridden still cascades a Transaction row via
     * TransactionFactory's own default 'tour_booking_id' => TourBooking::factory() —
     * a SEPARATE booking from the one passed in, since Laravel resolves each nested
     * factory independently. Track that phantom booking too.
     */
    protected function trackDisputeTransactionCascade($dispute): void
    {
        if (!$dispute->transaction_id) {
            return;
        }
        $this->createdTransactionIds[] = $dispute->transaction_id;
        $transactionBookingId = DB::table('transactions')->where('id', $dispute->transaction_id)->value('tour_booking_id');
        if ($transactionBookingId) {
            $this->createdBookingIds[] = $transactionBookingId;
        }
    }

    /** @test */
    public function booking_can_have_dispute_information()
    {
        $booking = TourBooking::factory()->create();
        $this->createdBookingIds[] = $booking->id;

        $dispute = Dispute::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'warning_needs_response',
            'reason' => 'fraudulent',
            'amount' => 50.00,
            'currency' => 'usd',
            'evidence_due_by' => now()->addDays(7),
        ]);
        $this->createdDisputeIds[] = $dispute->id;
        $this->trackDisputeTransactionCascade($dispute);

        $this->assertTrue($booking->hasActiveDispute());

        $activeDispute = $booking->getActiveDispute();
        $this->assertNotNull($activeDispute);
        $this->assertEquals($dispute->id, $activeDispute->id);

        $summary = $booking->getDisputeSummary();
        $this->assertNotNull($summary);
        $this->assertEquals($dispute->stripe_dispute_id, $summary['id']);
        $this->assertEquals(50.00, $summary['amount']);
        $this->assertEquals('usd', $summary['currency']);
    }

    /** @test */
    public function booking_without_dispute_returns_null()
    {
        $booking = TourBooking::factory()->create();
        $this->createdBookingIds[] = $booking->id;

        $this->assertFalse($booking->hasActiveDispute());

        $activeDispute = $booking->getActiveDispute();
        $this->assertNull($activeDispute);

        $summary = $booking->getDisputeSummary();
        $this->assertNull($summary);
    }

    /** @test */
    public function customer_dispute_history_is_accessible()
    {
        $customerId = DB::table('customers')->insertGetId([
            'phone_number' => '555-0100',
            'remember_token' => Str::random(60),
        ]);
        $this->createdCustomerIds[] = $customerId;

        $booking1 = TourBooking::factory()->create(['customer_id' => $customerId]);
        $this->createdBookingIds[] = $booking1->id;
        $booking2 = TourBooking::factory()->create(['customer_id' => $customerId]);
        $this->createdBookingIds[] = $booking2->id;

        $dispute1 = Dispute::factory()->create([
            'booking_id' => $booking1->id,
            'status' => 'lost',
            'reason' => 'fraudulent',
        ]);
        $this->createdDisputeIds[] = $dispute1->id;
        $this->trackDisputeTransactionCascade($dispute1);

        $dispute2 = Dispute::factory()->create([
            'booking_id' => $booking2->id,
            'status' => 'warning_needs_response',
            'reason' => 'product_unacceptable',
        ]);
        $this->createdDisputeIds[] = $dispute2->id;
        $this->trackDisputeTransactionCascade($dispute2);

        $customerDisputes = Dispute::whereHas('booking', function ($query) use ($customerId) {
            $query->where('customer_id', $customerId);
        })->with('booking')->get();

        $this->assertCount(2, $customerDisputes);
        $this->assertTrue($customerDisputes->contains('id', $dispute1->id));
        $this->assertTrue($customerDisputes->contains('id', $dispute2->id));
    }
}
