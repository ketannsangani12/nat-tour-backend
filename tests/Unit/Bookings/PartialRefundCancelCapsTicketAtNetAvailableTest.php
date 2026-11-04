<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: Partial Refund at cancel time is ONE combined dollar figure the admin types,
 * covering tour + add-ons together — anything beyond what the tour side actually has left
 * correctly spills over into refunding add-ons (see the $addonRemainder logic this test doesn't
 * touch). But PartialRefundCancelService's Cash/Invoiced/Wire-Transfer branch wrote the RAW,
 * uncapped $request->partial_refund_value as the ticket-side refund amount (both the `transactions`
 * row and the "Cancelled" history entry), instead of capping it at what the tour side actually had
 * available ($tourNetAvailable). So the portion that spilled over got recorded TWICE: once
 * (correctly) as an add-on refund, and again (wrongly) as ticket-side money that was never actually
 * on the ticket — e.g. a $225 ticket + $45 add-ons booking, cancelled with partial_refund_value=265
 * (i.e. "refund $265 total"), ended up with the ledger showing $265 refunded for the ticket PLUS
 * $40 for add-ons = $305 total against only $270 ever collected.
 */
class PartialRefundCancelCapsTicketAtNetAvailableTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_partial_refund_ticket_amount_is_capped_at_the_tour_net_available(): void
    {
        $this->authAsAdmin();

        // 3 tourists @ $75 = $225 ticket, Cash.
        $booking = (new CreateABooking())
            ->withTourists(3)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // 3 pax, each with their own $15 Photography Package -- $45 total, none refunded yet.
        $fieldIds = [];
        foreach ([830100, 830101, 830102] as $paxId) {
            $field = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
                'pax_id' => $paxId, 'field_label' => 'Photography Package',
                'pax_index' => 'pax-' . $paxId, 'unit_type' => 'price_per_pax', 'unit' => 1,
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            $fieldIds[] = $field->id;
        }

        // Every real addon payment also mirrors a row into the main `transactions` table
        // (TransactionService::storeAddonsPaymentInTransaction(), charge_type='addons') -- the
        // outer cancel()'s own upfront "is partial_refund_value <= total" validation sums ALL
        // Completed transactions regardless of charge_type, so this must exist for that gate to
        // pass the same way it does on a real booking.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => 45.00,
            'transaction_number' => '',
            'via' => 'Cash',
            'payment_type' => 0,
            'charge_type' => 'addons',
            'response_init' => '',
            'response_end' => '',
            'response_end_datetime' => now(),
            'status' => 'Completed',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'partial_refund',
                'partial_refund_value' => 265, // ticket ($225) + spillover into add-ons ($40)
                'is_cash' => 'Cash',
                'comment' => 'partial refund cap test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();
            $ticketDetails = json_decode($cancelledRow->details, true);
            $this->assertEquals(
                225.00,
                (float) $ticketDetails['amount'],
                'The ticket-side refund must be capped at the $225 actually available, not the raw $265 typed'
            );

            $addonRefundRows = TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)
                ->where('operation', 'refund')
                ->get();
            $addonTotalRefunded = (float) $addonRefundRows->sum(fn ($r) => $r->field_price + $r->field_fee + $r->option_price + $r->option_fee);
            $this->assertEquals(40.00, $addonTotalRefunded, 'The add-on spillover ($265 - $225) must still be refunded correctly');

            // Total refunded across ticket + add-ons must equal exactly what was typed, not more.
            $this->assertEquals(265.00, (float) $ticketDetails['amount'] + $addonTotalRefunded);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
