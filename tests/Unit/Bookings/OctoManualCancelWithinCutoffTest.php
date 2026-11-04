<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\OctoBooking;
use App\Models\TourBooking;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Coverage for the OCTO within-cutoff manual cancel feature (branch
 * feature-ota-cancel-booking-within-cutoff):
 *
 * Headout's own OCTO cancel call can get rejected by our cancellation-cutoff check even
 * though the guest already cancelled on Headout's platform, leaving our system out of sync.
 * isOctoBookingCancellationWithinCutoff() lets the dashboard's Cancel Booking button work for
 * these OCTO-linked, custom-charge-free bookings, routing them through
 * OctoRepository::cancelConfirmedOctoBooking() instead of the generic Cash/Card refund switch
 * (which doesn't understand an OTA `via` and would crash — see handleCancelRefundJobs()).
 */
class OctoManualCancelWithinCutoffTest extends TestCase
{
    protected array $cleanupCustomerIds = [];
    protected array $cleanupOctoBookingIds = [];

    protected function tearDown(): void
    {
        if ($this->cleanupOctoBookingIds) {
            OctoBooking::whereIn('id', $this->cleanupOctoBookingIds)->delete();
        }
        if ($this->cleanupCustomerIds) {
            DB::table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }
        parent::tearDown();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    private function createHeadoutAffiliate(): int
    {
        $id = DB::table('customers')->insertGetId([
            'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
            'name' => 'Headout Affiliate ' . uniqid(),
            'email' => 'headout-' . uniqid() . '@test.com',
            'is_affiliate' => 'Yes',
            'is_ota' => 1,
            'ota_id' => 'headout',
            'status' => 'Active',
            'remember_token' => '',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function linkOctoBooking(int $affiliateId, CreateABooking $booking, string $status = 'CONFIRMED'): OctoBooking
    {
        $octoBooking = OctoBooking::create([
            'uuid' => (string) Str::uuid(),
            'affiliate_id' => $affiliateId,
            'tour_slot_id' => $booking->slotTimeId,
            'tour_package_id' => $booking->packageId,
            'tour_booking_id' => $booking->bookingId,
            'status' => $status,
            'tourists' => 1,
            'infants' => 0,
            'unit_items' => [],
            'confirmed_at' => now(),
            'expire_at' => now()->addDay(),
        ]);
        $this->cleanupOctoBookingIds[] = $octoBooking->id;
        return $octoBooking;
    }

    // ── isOctoBookingCancellationWithinCutoff() ─────────────────────────────

    public function test_helper_returns_true_for_octo_linked_booking_with_no_custom_charge(): void
    {
        $affiliateId = $this->createHeadoutAffiliate();
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $this->linkOctoBooking($affiliateId, $booking);
            $tourBooking = TourBooking::find($booking->bookingId);

            $this->assertTrue(isOctoBookingCancellationWithinCutoff($tourBooking));
        } finally {
            $booking->cleanup();
        }
    }

    public function test_helper_returns_false_when_no_octo_booking_is_linked(): void
    {
        // Bokun also writes ota_reference_booking, but has no octo_bookings row.
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $tourBooking = TourBooking::find($booking->bookingId);

            $this->assertFalse(isOctoBookingCancellationWithinCutoff($tourBooking));
        } finally {
            $booking->cleanup();
        }
    }

    public function test_helper_returns_false_when_booking_has_a_custom_charge(): void
    {
        $affiliateId = $this->createHeadoutAffiliate();
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $this->linkOctoBooking($affiliateId, $booking);

            DB::table('transactions')->insert([
                'tour_booking_id' => $booking->bookingId,
                'amount' => 20.00,
                'charge_type' => 'custom_charge',
                'via' => 'Cash',
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $tourBooking = TourBooking::find($booking->bookingId);

            $this->assertFalse(isOctoBookingCancellationWithinCutoff($tourBooking));
        } finally {
            $booking->cleanup();
        }
    }

    // ── TourBookingRepository::cancel() — OCTO-eligible happy path ──────────

    public function test_cancel_of_octo_eligible_booking_syncs_both_tour_booking_and_octo_booking(): void
    {
        $this->authAsAdmin();
        $affiliateId = $this->createHeadoutAffiliate();
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $octoBooking = $this->linkOctoBooking($affiliateId, $booking);

            $request = new Request();
            $request->merge([
                'api' => 1,
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'comment' => 'Guest cancelled on Headout within our cutoff window',
            ]);

            app(TourBookingRepository::class)->cancel($request, $booking->bookingId);

            $this->assertSame(
                'Cancelled',
                DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status')
            );
            $this->assertSame(
                'CANCELLED',
                $octoBooking->refresh()->status,
                'The linked octo_bookings row must also flip to CANCELLED so our OCTO state stays in sync.'
            );

            $refundRow = DB::table('transactions')
                ->where('tour_booking_id', $booking->bookingId)
                ->where('status', 'Refund')
                ->first();
            $this->assertNotNull($refundRow, 'Cancelling must create a Refund transaction row.');
            $this->assertEquals(100.00, (float) $refundRow->amount);
            $this->assertSame('Headout', $refundRow->via, 'Refund must be attributed to the OTA, not Cash/Card.');

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $booking->bookingId)
                ->where('operation', 'Cancelled')
                ->first();
            $this->assertNotNull($historyRow);
            $details = json_decode($historyRow->details, true);
            $this->assertSame('100% Refund', $details['refund_type']);
        } finally {
            $booking->cleanup();
        }
    }

    public function test_cancel_of_octo_eligible_booking_records_ticket_refund_label(): void
    {
        $this->authAsAdmin();
        $affiliateId = $this->createHeadoutAffiliate();
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $this->linkOctoBooking($affiliateId, $booking);

            $request = new Request();
            $request->merge([
                'api' => 1,
                'cancel_booking' => 1,
                'refund_type' => 'full_refund',
                'comment' => 'Guest cancelled on Headout within our cutoff window',
            ]);

            app(TourBookingRepository::class)->cancel($request, $booking->bookingId);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $booking->bookingId)
                ->where('operation', 'Cancelled')
                ->first();
            $details = json_decode($historyRow->details, true);
            $this->assertSame('Ticket Refund', $details['refund_type']);
        } finally {
            $booking->cleanup();
        }
    }

    // ── Guard rejections ────────────────────────────────────────────────────

    public function test_cancel_rejects_non_octo_ota_booking(): void
    {
        $this->authAsAdmin();

        // Simulates a Bokun-sourced booking: ota_reference_booking is set, but there is
        // no linked octo_bookings row -- must still be blocked from this dashboard.
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $request = new Request();
            $request->merge([
                'api' => 1,
                'cancel_booking' => 1,
                'refund_type' => 'full_refund',
                'comment' => 'attempted cancel',
            ]);

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('This OTA booking cannot be cancelled from this dashboard.');

            app(TourBookingRepository::class)->cancel($request, $booking->bookingId);
        } finally {
            $this->assertSame(
                'Booked',
                DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'),
                'A rejected cancel attempt must leave the booking untouched.'
            );
            $booking->cleanup();
        }
    }

    public function test_cancel_rejects_octo_booking_with_custom_charge(): void
    {
        $this->authAsAdmin();
        $affiliateId = $this->createHeadoutAffiliate();
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Headout')
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            $this->linkOctoBooking($affiliateId, $booking);

            DB::table('transactions')->insert([
                'tour_booking_id' => $booking->bookingId,
                'amount' => 20.00,
                'charge_type' => 'custom_charge',
                'via' => 'Cash',
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'api' => 1,
                'cancel_booking' => 1,
                'refund_type' => 'full_refund',
                'comment' => 'attempted cancel',
            ]);

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('This OTA booking cannot be cancelled from this dashboard.');

            app(TourBookingRepository::class)->cancel($request, $booking->bookingId);
        } finally {
            $this->assertSame(
                'Booked',
                DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('status'),
                'A rejected cancel attempt must leave the booking untouched.'
            );
            $booking->cleanup();
        }
    }
}
