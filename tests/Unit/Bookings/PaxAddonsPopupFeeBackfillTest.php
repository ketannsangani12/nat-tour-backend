<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: processAddonFinancialTransactions() deliberately records a $0 PER-ITEM fee on a
 * field's own tour_booking_addon_transactions row whenever an add/remove edit nets to a charge or
 * refund with an "offset" (hasChargeOffset/hasRefundOffset there) — the real fee for that edit is
 * booked once in aggregate against the booking's own Payment/History rows, never split back onto
 * one specific field (see UpdateBookingAddonsNetChargeOffsetPiMismatchTest, which locks in that
 * $0-per-item behavior as required for Stripe-refund safety — it must NOT be "fixed" on the write
 * side).
 *
 * That left the "Passenger Add-ons" popup (getBookingPaxAddons()) showing those specific pax rows
 * with no "+ fee" line and an artificially low "Charged Total", while an identical add-on selected
 * by a pax whose field was never touched by an offset displayed its real fee correctly — same
 * item, same price, inconsistent display depending on unrelated edit history.
 */
class PaxAddonsPopupFeeBackfillTest extends TestCase
{
    public function test_charged_fee_is_backfilled_for_display_when_the_ledger_row_carries_a_genuine_zero(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Fee Backfill Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Three pax, all with the SAME $15 add-on, but only the third one's own ledger row still
        // carries its real fee — the first two simulate fields left behind by a prior
        // add/remove edit that netted to an offset (real-world: an admin removed one pax's
        // add-on while adding it for two others in the same request).
        $fieldIds = [];
        $paxFields = [];
        foreach ([0, 1, 2] as $ordinal) {
            $field = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
                'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
                'pax_index' => "fee-backfill-pax-{$ordinal}", 'person_ordinal' => $ordinal,
                'unit_type' => 'price_per_pax', 'unit' => 1,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            $fieldIds[] = $field->id;
            $paxFields[$ordinal] = $field;

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => "pi_fee_backfill_{$ordinal}",
                'field_price' => 15.00,
                // Ordinal 2 is the untouched original charge (real fee already correct);
                // ordinals 0/1 simulate the offset-affected fields (per-item fee zeroed by design).
                'field_fee' => $ordinal === 2 ? 1.50 : 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
        }

        try {
            $repo = new TourBookingAddonRepository();
            $request = new Request();
            $request->merge(['booking_id' => $bookingId, 'rate_groups' => [$booking->rateId]]);

            $response = json_decode($repo->getBookingPaxAddons($request)->getContent(), true);

            $this->assertCount(3, $response[0]['pax']);
            foreach ($response[0]['pax'] as $pax) {
                $this->assertEquals(15.00, $pax['charged_price'], "Ordinal {$pax['person_ordinal']} price mismatch");
                $this->assertEquals(1.50, $pax['charged_fee'], "Ordinal {$pax['person_ordinal']} fee must be backfilled to match what the same $15 add-on charges everywhere else");

                $node = $pax['tree'][0];
                $this->assertEquals(15.00, $node['paid_price']);
                $this->assertEquals(1.50, $node['paid_fee'], "Ordinal {$pax['person_ordinal']} node-level fee must also be backfilled so the popup's '+ fee' line renders");
            }
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            AddonField::withTrashed()->where('id', $photoField->id)->forceDelete();
            AddonForm::withTrashed()->where('id', $form->id)->forceDelete();
            $booking->cleanup();
        }
    }
}
