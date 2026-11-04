<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: the fix in [[project_change_people_addon_bundled_remaining_link_fix]] (Rounds 2-3)
 * only wired the "drop the superseded pending remaining_payment link item" step into the CARD
 * Change People branch. An Invoiced or Cash Change People edit that upgrades the SAME kind of
 * never-paid addon (still sitting on a pending link item from an earlier "Pay with Link" pax
 * addition) had no equivalent -- calculatePricedAddonDelta() correctly charges the full new price
 * either way (that fix is payment-method-agnostic), but nothing ever cancelled/shrank the stale
 * pending item for a non-Card settlement, since non-Card never calls
 * RemainingPaymentService::finalizeItems() (the only place that previously drove Card's own
 * cleanup). Left un-dropped, the stale item would sit on the link indefinitely and could later be
 * separately finalized (e.g. if the customer ever paid the remaining link), producing the exact
 * same double-ledger-entry symptom, just deferred.
 *
 * Fixed by adding the identical "drop superseded" call to both non-Card
 * ChangePeopleAddonService::settleFromDelta() call sites in
 * TourBookingRepository::changePeopleBooking() (the "no transaction yet" Invoiced fast path, and
 * the general Cash/Invoiced flow) -- with recalculate=true (unlike Card's recalculate=false),
 * since no companion finalizeItems() call exists for a non-Card settlement to recompute the link's
 * amount/token/email afterward; this mirrors TourBookingAddonRepository::
 * syncPendingAddonLinkForEditedFields()'s own non-card branch exactly.
 *
 * This test proves syncPendingAddonLinkItemsForUpdatedFields(..., recalculate: true) both cancels
 * the superseded item AND shrinks the link's own amount down to just the remaining (ticket) item
 * -- the correct outcome for a non-Card settlement with no finalizeItems() to do this instead.
 */
class ChangePeopleAddonInvoicedSupersedesPendingLinkTest extends TestCase
{
    public function test_sync_with_recalculate_shrinks_the_link_when_invoiced_settlement_supersedes_the_pending_addon(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Cash')->create();
        $groupId = (string) $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $booking->bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create(['tour_package_id' => $booking->packageId, 'name' => 'Invoiced Ordering Form ' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => true, 'price' => 15.00, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $standard = AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Standard', 'value' => 'standard', 'additional_fee' => true, 'price' => 5.00, 'sort_order' => 0]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Premium', 'value' => 'premium', 'additional_fee' => true, 'price' => 10.00, 'sort_order' => 1]);

        $paxIndex = 'test-invoiced-ordering-0';
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $standard->id, 'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndex, 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 102.46,
            'status' => 'Unpaid', 'override_status' => 'Yes', 'token' => Str::random(64),
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $ticketItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people', 'amount' => 80.50, 'status' => 'Unpaid',
            'details' => ['tourists' => 1, 'ticket_cost' => 73.3, 'platform_fees' => 7.2, 'tour_booking_id' => $booking->bookingId],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        // Real production shape (tenant `kens`, booking 348698, link item 59).
        $addonItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'amount' => 21.96, 'status' => 'Unpaid',
            'details' => [
                'booking_id' => $booking->bookingId, 'form_id' => (string) $form->id, 'platform_fees' => 1.96,
                'field_to_db' => [$field->id . '_' . $paxIndex => $row->id],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 20, 'to_refund' => [],
                    'to_charge' => [[
                        'label' => 'Photography Package (Standard)', 'field_id' => $field->id, 'field_fee' => 1.47,
                        'old_db_id' => null, 'pax_index' => $paxIndex, 'option_fee' => 0.49,
                        'field_price' => 15, 'option_price' => 5, 'charge_amount' => 20,
                        'field_additional_fee' => true, 'option_additional_fee' => true,
                    ]],
                ],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            // The fixed call for the non-Card (Invoiced/Cash) settlement path, matching what
            // TourBookingRepository::changePeopleBooking() now calls before settleFromDelta().
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields(
                $booking->bookingId,
                [$row->id],
                [$row->id],
                true
            );

            $this->assertEquals(
                'Cancelled',
                TourBookingPaymentLinkItem::find($addonItem->id)->status,
                'The stale addon item must be cancelled so it can never be separately finalized later.'
            );
            $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($ticketItem->id)->status);

            // recalculate=true must shrink the link's own total down to just the remaining
            // (ticket) item -- $102.46 -> $80.50 -- unlike Card's recalculate=false, since there's
            // no companion finalizeItems() call for a non-Card settlement to do this instead.
            $freshLink = TourBookingPaymentLink::find($link->id);
            $this->assertEqualsWithDelta(
                80.50,
                (float) $freshLink->amount,
                0.01,
                'With no finalizeItems() call for a non-Card settlement, recalculate=true must be the thing that shrinks the link.'
            );
            $this->assertEquals('Unpaid', $freshLink->status, 'The link stays Unpaid -- the remaining ticket item is still owed.');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            TourBookingPaymentLink::where('id', $link->id)->delete();
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $row->id)->delete();
            TourBookingAddonField::withTrashed()->where('id', $row->id)->forceDelete();
            DB::table('addon_field_options')->where('field_id', $field->id)->delete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
