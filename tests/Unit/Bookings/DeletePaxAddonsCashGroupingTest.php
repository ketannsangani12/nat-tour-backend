<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: removing a pax with 2+ cash-paid add-ons via the direct "Edit Booking" delete flow
 * (editBooking() -> TourBookingAddonRepository::deletePaxAddons()) wrote a SEPARATE
 * tour_booking_history/transactions row PER ADD-ON FIELD instead of one combined entry, because
 * its PI grouping key fell back to the addon field id (unique per field) whenever the field had
 * no real Stripe PaymentIntent — true for every cash-paid add-on, since
 * AddonBookingService::createAddonTransactionsFromFields() stores transaction_id=null for cash.
 * A Card booking's add-ons all share one real PaymentIntent string, so they always grouped
 * correctly — only Cash ever hit the fallback and split.
 *
 * refundAddonsByBookingId() had already been fixed for the identical bug (group by payment
 * method when there's no real PI) — deletePaxAddons() (and chargeAddonFields(), the other addon
 * refund path) still had the old, buggy fallback.
 */
class DeletePaxAddonsCashGroupingTest extends TestCase
{
    public function test_removing_a_cash_paid_pax_with_multiple_addons_writes_one_combined_refund_entry(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Cash Grouping Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $sweaterField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sweater', 'type' => 'dropdown',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 30.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);

        $photoRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
            'pax_id' => 910100, 'field_label' => 'Photography Package', 'option_label' => null,
            'pax_index' => 'cash-grouping-0', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $sweaterRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $sweaterField->id,
            'pax_id' => 910100, 'field_label' => 'Sweater', 'option_label' => 'X-Large',
            'pax_index' => 'cash-grouping-0', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        // Cash addon original charge rows: transaction_id = NULL, matching what
        // AddonBookingService::createAddonTransactionsFromFields() actually stores for cash.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $photoRow->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0, 'field_additional_fee' => true, 'option_additional_fee' => false,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $sweaterRow->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 30.00, 'option_fee' => 2.95, 'field_additional_fee' => false, 'option_additional_fee' => true,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons(
                $bookingId,
                '[]',
                Constants::ADDONS_TICKET_REFUND,
                null,
                null,
                [$photoRow->id, $sweaterRow->id]
            );

            $historyRows = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->where('operation', 'Refund')
                ->get();
            $this->assertCount(
                1,
                $historyRows,
                'Both cash-paid add-ons removed together in one call must write ONE combined history entry, not one per field'
            );

            $details = json_decode($historyRows->first()->details, true);
            $this->assertCount(2, $details['addons'], 'The single combined entry must still list both removed add-ons');
            $names = array_column($details['addons'], 'name');
            $this->assertContains('Photography Package', $names);
            $this->assertContains('Sweater — X-Large', $names);

            $txRows = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->where('status', 'Refund')
                ->get();
            $this->assertCount(1, $txRows, 'Both cash-paid add-ons must write ONE combined transactions ledger row, not one per field');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$photoRow->id, $sweaterRow->id])->forceDelete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->whereIn('id', [$photoField->id, $sweaterField->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
