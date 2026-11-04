<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: TourBookingRepository::booking()'s `$historyPaymentTime` lookup ("the booking's
 * first Payment-operation history row") is used to derive `$checkAmount` for the "Booking Total"
 * display on tour-booking-info.blade.php. It queried `operation = 'Payment'` WITHOUT excluding
 * `charge_type = 'addons'` — but an add-on's own settlement (or even just the $0 "still open, Pay
 * Later" placeholder logged when a pax add-on is first added) ALSO writes an operation='Payment'
 * history row tagged charge_type='addons'. Whenever that row happened to have a LOWER id than the
 * booking's real ticket-side Payment row (e.g. an add-on added, then something briefly touched it,
 * before the ticket itself was ever actually paid), $historyPaymentTime silently picked the WRONG
 * (addon, often $0) row instead — and the blade's own `!empty($tourBookingHistory)` gate around
 * consuming it was ALSO broken (an Eloquent Collection is never "empty()" even with zero rows), so
 * this wrong row's amount always leaked into $checkAmount, understating "Booking Total" by exactly
 * the real ticket amount.
 *
 * Reproduces the exact reported bug: booking_id 337786's real history had an earlier
 * operation='Payment', charge_type='addons', amount=0 row (id N) followed by the real ticket
 * Payment row (id N+1, amount=365.92) — $historyPaymentTime picked row N, making "Booking Total"
 * render as $61.50 (just the add-ons total) instead of $427.42.
 */
class HistoryPaymentTimeAddonExclusionTest extends TestCase
{
    public function test_history_payment_time_skips_an_earlier_addon_payment_row_and_picks_the_real_ticket_payment(): void
    {
        // withoutHistory() — the fixture's own default 'Payment' history row would otherwise be
        // the oldest charge_type=NULL row and mask what this test is isolating.
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->withoutHistory()->create();

        // Reuse an existing admin: a factory admin was never deleted, adding one per run.
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->assertSame($admin->id, Auth::id());

        // The phantom/earlier addon-side "Payment" row — lower id, charge_type='addons', $0.
        $addonHistoryId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $booking->bookingId,
            'operation' => 'Payment',
            'charge_type' => 'addons',
            'ticket_cost' => 0, 'discount' => 0, 'permit_fees' => 0, 'processing_fees' => 0,
            'platform_fees' => 0, 'partner_fees' => 0, 'tourists' => 1,
            'details' => json_encode(['amount' => 0, 'fees' => 0]),
            'is_processed' => 1,
            'operation_at' => now()->subMinute(),
            'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute(),
        ]);

        // The REAL ticket-side Payment row — higher id, charge_type NULL, the real amount.
        $ticketHistoryId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $booking->bookingId,
            'operation' => 'Payment',
            'charge_type' => null,
            'ticket_cost' => 333.20, 'discount' => 0, 'permit_fees' => 0, 'processing_fees' => 0,
            'platform_fees' => 32.72, 'partner_fees' => 0, 'tourists' => 4,
            'details' => json_encode(['amount' => 365.92, 'fees' => 32.72]),
            'is_processed' => 1,
            'operation_at' => now(),
            'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $repo = app(TourBookingRepository::class);
            $vars = $repo->booking($booking->bookingId, false);

            $this->assertArrayHasKey('historyPaymentTime', $vars);
            $this->assertNotEmpty($vars['historyPaymentTime'], 'historyPaymentTime must resolve to a row, not be skipped entirely.');
            $this->assertSame(
                $ticketHistoryId,
                $vars['historyPaymentTime']->id,
                'historyPaymentTime must pick the real ticket-side Payment row, not an earlier addon-side one.'
            );

            $details = json_decode($vars['historyPaymentTime']->details);
            $this->assertEquals(365.92, $details->amount, 'The resolved row must carry the real ticket payment amount, not the addon placeholder\'s $0.');
        } finally {
            DB::table('tour_booking_history')->whereIn('id', [$addonHistoryId, $ticketHistoryId])->delete();
            $booking->cleanup();
        }
    }
}
