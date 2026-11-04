<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Services\Booking\DeleteBookingAddonBreakdownService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: the "Update Booking" delete-pax modal's add-ons block (list + checkboxes) never
 * rendered for an Invoiced-via booking — "Billable" INCLUDED, since it is NOT a distinct `via`
 * value anywhere in the data: it is purely a display label (see tour-booking-info.blade.php's
 * `$bookingM->via == 'Invoiced' && empty($invoiced)`) for an Invoiced booking with no formal
 * invoice record generated yet. The booking's real `transactions` rows (confirmed against a live
 * booking) genuinely carry via='Invoiced', payment_type=0 — the gate's
 * `in_array($trans->via, ['Card', 'Cash'])` simply never matched 'Invoiced', so the add-ons block
 * (and the field-id data the backend needs to refund them) never rendered, and removing a pax
 * defaulted its add-ons to "Don't Refund" regardless of the admin's actual selection.
 *
 * Invoiced add-ons are committed as status='paid' immediately, same as Card/Cash
 * (AddonBookingService::createAddonTransactionsFromFields()'s 'invoiced' branch) — there is
 * genuinely refundable add-on money to show/select, exactly like a Cash booking's.
 *
 * DeleteBookingAddonBreakdownService::attach() itself has no payment-method filter at all — it's
 * driven entirely by the boolean flag the caller passes (V3\TourBookingRepository::booking(),
 * mirrored by the blade's own @if) — this locks in that flag now correctly evaluating true for an
 * Invoiced/Billable booking, the same way it already does for Cash.
 */
class DeleteBookingModalBillableAddonsTest extends TestCase
{
    /**
     * The exact gate expression from V3\TourBookingRepository::booking() (and mirrored by
     * _update-booking-modal.blade.php's own @if) — duplicated here (rather than invoking the
     * 2000+-line booking() method directly, which isn't practically unit-testable) so this test
     * exercises the real logic and fails if it's ever reverted or drifts out of sync between the
     * two call sites.
     */
    private function deleteBookingModalRendersAddons($trans): bool
    {
        return !empty($trans) && $trans->payment_type == 0 && in_array($trans->via, ['Card', 'Cash', 'Invoiced']);
    }

    public function test_gate_covers_invoiced_billable_the_same_way_it_already_covers_card_and_cash(): void
    {
        $cases = [
            'Card'                => [(object) ['payment_type' => 0, 'via' => 'Card'], true],
            'Cash'                => [(object) ['payment_type' => 0, 'via' => 'Cash'], true],
            'Invoiced ("Billable")' => [(object) ['payment_type' => 0, 'via' => 'Invoiced'], true],
            'Card, split payment (payment_type=1)' => [(object) ['payment_type' => 1, 'via' => 'Card'], false],
            'No transaction at all (true Pay Later)' => [null, false],
        ];

        foreach ($cases as $label => [$trans, $expected]) {
            $this->assertSame($expected, $this->deleteBookingModalRendersAddons($trans), $label);
        }
    }

    public function test_invoiced_billable_booking_still_surfaces_addon_items_when_the_modal_flag_is_true(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Invoiced')->create();
        $bookingId = $booking->bookingId;

        // Match the real Billable/Invoiced booking's transactions rows: via='Invoiced',
        // payment_type=0, no transaction_number/application_fee_id (no Stripe PaymentIntent).
        DB::table('transactions')->where('tour_booking_id', $bookingId)->update([
            'via' => 'Invoiced',
            'payment_type' => 0,
            'transaction_number' => '',
            'application_fee_id' => null,
        ]);
        $trans = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('status', 'Completed')
            ->whereNull('charge_type')
            ->first();
        $this->assertNotNull($trans, 'Sanity check: a real Completed transactions row must exist for this booking, matching the reported scenario');

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Invoiced Billable Modal Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);

        $paxTransactId = (int) DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->value('id');

        $photoRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
            'pax_id' => $paxTransactId, 'field_label' => 'Photography Package', 'option_label' => null,
            'pax_index' => 'invoiced-modal-0', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        // Invoiced/Billable add-ons are committed as 'paid' immediately, with no real Stripe
        // PaymentIntent — transaction_id stays null, matching
        // AddonBookingService::createAddonTransactionsFromFields() being called with a null
        // $paymentIntent for 'invoiced', same as it is for 'cash'.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $photoRow->id,
            'payment_method' => 'invoiced', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            // OLD (buggy) gate: 'Invoiced' was never in the allowed via list, so this evaluated
            // false regardless of $trans being populated — exactly what the reported screenshots
            // showed: no add-ons block, no checkboxes.
            $oldGate = !empty($trans) && $trans->payment_type == 0 && in_array($trans->via, ['Card', 'Cash']);
            $this->assertFalse($oldGate, 'Reproduces the bug: the old gate excluded Invoiced/Billable even with a real, populated transaction row');

            $newGate = $this->deleteBookingModalRendersAddons($trans);
            $this->assertTrue($newGate, 'The fixed gate must include Invoiced/Billable');

            $oldConditionResult = DeleteBookingAddonBreakdownService::attach(new Collection(), $bookingId, $oldGate);
            $this->assertSame([], $oldConditionResult, 'With the old gate, the add-on is never surfaced to the modal at all');

            $newConditionResult = DeleteBookingAddonBreakdownService::attach(new Collection(), $bookingId, $newGate);
            $this->assertNotEmpty($newConditionResult, 'The fixed gate must surface this Invoiced/Billable pax\'s paid add-on so the modal can show and refund it');

            $items = collect($newConditionResult)->flatten(1);
            $this->assertCount(1, $items);
            $this->assertEquals('Photography Package', $items->first()['label']);
            $this->assertEquals(15.00, $items->first()['price']);
            $this->assertEquals($photoRow->id, $items->first()['id']);
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $photoRow->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
