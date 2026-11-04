<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: cancelling a Cash/Invoiced/Wire Transfer booking that has paid add-ons refunded the
 * ticket amount but silently left the add-on charges untouched. WeatherRefundCancelService (100%
 * refund) and TicketRefundCancelService (full refund) only called
 * TourBookingAddonRepository::refundAddonsByBookingId() from their Card branch — the Cash/
 * Invoiced/Wire Transfer branch returned before ever reaching it, even though the exact same
 * addon-repository call is payment-method agnostic (it only calls Stripe for rows whose own
 * payment_method is 'card').
 */
class CancelBookingAddonRefundTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function createPaidCashAddon(int $bookingId, string $paxIndex, float $price = 40.00): TourBookingAddonField
    {
        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 900100, 'field_label' => 'Camera Rental Type',
            'option_label' => 'DSLR Camera', 'pax_index' => $paxIndex,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => $price, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        return $field;
    }

    public function test_100_percent_refund_on_cash_booking_also_refunds_the_addon(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->createPaidCashAddon($bookingId, 'cancel-100-cash');

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => '100% refund cancel test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertNotNull($refundRow, 'Cancelling with 100% refund must also refund the paid addon');
            $this->assertEquals(40.00, (float) $refundRow->field_price);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_full_refund_on_cash_booking_also_refunds_the_addon(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->createPaidCashAddon($bookingId, 'cancel-full-cash');

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'full_refund',
                'is_cash' => 'Cash',
                'comment' => 'Full refund cancel test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertNotNull($refundRow, 'Cancelling with full refund must also refund the paid addon');
            $this->assertEquals(40.00, (float) $refundRow->field_price);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_dont_refund_on_cash_booking_settles_the_addon_without_paying_it_out(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->createPaidCashAddon($bookingId, 'cancel-dontrefund-cash');

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'dont_refund',
                'is_cash' => 'Cash',
                'comment' => "Don't refund cancel test",
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertNotNull($refundRow, "Cancelling with don't refund must still settle the addon in history");
            $this->assertEquals(0.00, (float) $refundRow->field_price, "Don't Refund keeps the addon money, so nothing is paid out");
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: refundAddonsByBookingId() grouped refund history by
     * `transaction_id ?? 'booking_' . field_id`. Card-paid add-ons share one real Stripe PI so they
     * already collapsed into a single history entry; cash-paid add-ons have no PI, so the fallback
     * key was unique per field and cancelling 3 cash-paid add-ons together wrote 3 separate
     * "Refund" history rows instead of the single combined entry Card bookings get (and that the
     * original "Full Payment" charge itself gets).
     */
    public function test_multiple_cash_addons_refunded_together_produce_a_single_history_entry(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        $fields = [
            $this->createPaidCashAddon($bookingId, 'cancel-multi-cash-1', 15.00),
            $this->createPaidCashAddon($bookingId, 'cancel-multi-cash-2', 15.00),
            $this->createPaidCashAddon($bookingId, 'cancel-multi-cash-3', 15.00),
        ];
        $fieldIds = array_map(fn ($f) => $f->id, $fields);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'Multi cash addon refund test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            // Each original charge still gets its own refund transaction row (money-tracking stays
            // per-field) ...
            $refundRows = TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)
                ->where('operation', 'refund')
                ->get();
            $this->assertCount(3, $refundRows, 'Each cash addon charge should still get its own refund row');

            // ... but the customer-facing ledger/history should combine them into one entry, same
            // as a Card cancellation would.
            $historyRows = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->get();

            $this->assertCount(1, $historyRows, 'Cash addon refunds from one cancellation must merge into a single history entry');

            $details = json_decode($historyRows->first()->details, true);
            $this->assertCount(3, $details['addons'], 'Combined history entry must list all 3 refunded addons');
            $this->assertEquals(45.00, (float) $details['amount'], 'Combined history entry must total all 3 refunds');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
